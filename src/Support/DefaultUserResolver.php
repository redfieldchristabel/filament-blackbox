<?php

namespace Blackbox\FilamentBlackbox\Support;

use Blackbox\FilamentBlackbox\Contracts\UserResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Default user filter lookup: a bounded LIKE search on the user model of EVERY
 * configured auth guard's provider (see {@see userModelClasses()}), over the
 * columns in `blackbox.users.search_columns` (columns the table does not have
 * are skipped). Labels come from {@see UserLabel}.
 *
 * Several guards may use different user models, so each option is keyed by the
 * audit's own user morph as owen-it stores it on every row —
 * `<user_type>:<user_id>` (the `user_type`/`user_id` columns under
 * `audit.user.morph_prefix`). Two users with the same id on different models
 * stay distinguishable, in the filter options as well as in the query.
 */
class DefaultUserResolver implements UserResolver
{
    protected int $limit = 50;

    public function search(string $search): array
    {
        $search = trim($search);
        $results = [];

        foreach (self::userModelClasses() as $model) {
            $instance = new $model;
            $query = $model::query()->orderBy($instance->getQualifiedKeyName());

            if ($search !== '') {
                $columns = $this->searchColumns($instance);

                if ($columns === []) {
                    // Nothing searchable: only an exact key lookup makes sense.
                    // A non-numeric term against an integer key would make
                    // PostgreSQL throw (`invalid input syntax for type
                    // bigint`), so key-incompatible terms return no results
                    // from this model instead.
                    if ($instance->getKeyType() === 'int' && ! ctype_digit($search)) {
                        continue;
                    }

                    $query->whereKey($search);
                } else {
                    $query->where(function (Builder $builder) use ($instance, $columns, $search): void {
                        foreach ($columns as $column) {
                            // lower() on BOTH sides: a plain `like` is
                            // case-sensitive on PostgreSQL (sqlite only looks
                            // case-insensitive for ASCII), while Filament's
                            // own searchable selects match case-insensitively
                            // (ilike on pg). Wildcards are escaped so a `%` or
                            // `_` in the term matches literally, never the
                            // whole table.
                            $builder->orWhereRaw(
                                sprintf(
                                    "lower(%s) like lower(?) escape '!'",
                                    $instance->getConnection()->getQueryGrammar()->wrap($instance->qualifyColumn($column)),
                                ),
                                ['%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%'],
                            );
                        }
                    });
                }
            }

            foreach ($query->limit($this->limit)->get() as $user) {
                $results[self::filterValue($user)] = UserLabel::forUser($user);
            }
        }

        return $results;
    }

    public function labels(array $ids): array
    {
        $bare = [];
        $typed = [];

        foreach ($ids as $value) {
            $parsed = self::parseFilterValue((string) $value);

            if ($parsed['type'] === null) {
                $bare[$parsed['id']] = $parsed['id'];
            } else {
                $typed[$parsed['type']][$parsed['id']] = $parsed['id'];
            }
        }

        // One query per user model for ALL of its ids (previously one query
        // per selected id, and per model until found).
        $typedUsers = [];
        $bareUsers = [];

        foreach (self::userModelClasses() as $model) {
            $morphClass = (new $model)->getMorphClass();
            $wanted = array_values(array_unique([
                ...array_values($typed[$morphClass] ?? []),
                ...array_values($bare),
            ]));

            if ($wanted === []) {
                continue;
            }

            foreach ($model::query()->whereKey($wanted)->get() as $user) {
                $key = (string) $user->getKey();

                if (isset($typed[$morphClass][$key])) {
                    $typedUsers[$morphClass][$key] = $user;
                }

                // A bare id belongs to the first model (guard order) that has it.
                if (isset($bare[$key])) {
                    $bareUsers[$key] ??= $user;
                }
            }
        }

        $labels = [];

        foreach ($ids as $value) {
            $parsed = self::parseFilterValue((string) $value);

            $user = $parsed['type'] !== null
                ? ($typedUsers[$parsed['type']][$parsed['id']] ?? null)
                : ($bareUsers[$parsed['id']] ?? null);

            // Users that no longer exist keep a readable label.
            $labels[$value] = $user !== null ? UserLabel::forUser($user) : '#' . $parsed['id'];
        }

        return $labels;
    }

    /**
     * The user models to search, resolved the laravel-auditing way: the auth
     * guards of `audit.user.guards` (the same guards laravel-auditing tries at
     * audit time), each resolved through `auth.guards.<guard>.provider` to the
     * model of `auth.providers.<name>.model`. Every configured guard is
     * included, in guard order and deduplicated, because audits may belong to
     * users of any of them. No provider name is hardcoded — applications with
     * differently named providers resolve their own models.
     *
     * @return array<int, class-string<Model>>
     */
    public static function userModelClasses(): array
    {
        /** @var array<int, mixed> $guards */
        $guards = (array) (config('audit.user.guards') ?: [(string) config('auth.defaults.guard', 'web')]);

        $models = [];

        foreach ($guards as $guard) {
            if (! is_string($guard) || $guard === '') {
                continue;
            }

            $provider = config("auth.guards.{$guard}.provider");

            if (! is_string($provider) || $provider === '') {
                continue;
            }

            $model = config("auth.providers.{$provider}.model");

            if (is_string($model) && is_subclass_of($model, Model::class) && ! in_array($model, $models, true)) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * The first user model, for callers that need "the" user model; the user
     * filter itself covers every model of {@see userModelClasses()}.
     */
    public static function userModelClass(): ?string
    {
        return self::userModelClasses()[0] ?? null;
    }

    /**
     * The filter option value of a user: its owen-it user morph as
     * `<user_type>:<user_id>`, exactly what every audit row stores, so ids
     * from different user models never conflate.
     */
    public static function filterValue(Model $user): string
    {
        return $user->getMorphClass() . ':' . $user->getKey();
    }

    /**
     * Splits a filter value into its user morph parts. A bare id (custom
     * resolvers, legacy values) carries no type.
     *
     * @return array{type: ?string, id: string}
     */
    public static function parseFilterValue(string $value): array
    {
        $separator = strrpos($value, ':');

        if ($separator === false) {
            return ['type' => null, 'id' => $value];
        }

        $type = substr($value, 0, $separator);
        $id = substr($value, $separator + 1);

        if ($type === '' || $id === '') {
            return ['type' => null, 'id' => $value];
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * @return array<int, string>
     */
    protected function searchColumns(Model $model): array
    {
        /** @var array<int, string> $configured */
        $configured = (array) config('blackbox.users.search_columns', ['name', 'email']);

        // One column listing per model (kept in configured order); columns the
        // table does not have are dropped.
        $existing = Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());

        return array_values(array_intersect($configured, $existing));
    }
}
