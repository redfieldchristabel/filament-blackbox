<?php

namespace Blackbox\FilamentBlackbox;

use Blackbox\FilamentBlackbox\Contracts\UserResolver;
use Blackbox\FilamentBlackbox\Support\AuditableAttributes;
use Blackbox\FilamentBlackbox\Support\DefaultUserResolver;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use OwenIt\Auditing\Models\Audit;

class FilamentBlackbox
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAudits(array $filters = [], int $perPage = 10): CursorPaginator
    {
        // Get the configured audit model class from the audit package
        $auditModel = config('audit.implementation', Audit::class);

        // The audit model's own created-at column (renamed timestamp columns
        // supported); null when the model reports no timestamp column at all.
        $audit = new $auditModel;
        $createdAtColumn = AuditableAttributes::auditCreatedAtColumn($audit);

        // Start the query using the resolved class
        $query = $auditModel::query()->with(['user', 'auditable']);

        if (! empty($filters['users'])) {
            // Filter values name the audit's own user morph as owen-it stores
            // it (`<user_type>:<user_id>` under `audit.user.morph_prefix`); a
            // bare id (custom resolver) still matches on the id alone. Either
            // way, users of different models with the same id never conflate.
            $morphPrefix = (string) config('audit.user.morph_prefix', 'user');
            $plain = [];
            $typed = [];

            foreach ((array) $filters['users'] as $value) {
                $parsed = DefaultUserResolver::parseFilterValue((string) $value);

                if ($parsed['type'] === null) {
                    $plain[] = $parsed['id'];
                } else {
                    $typed[] = $parsed;
                }
            }

            if ($plain !== [] || $typed !== []) {
                $query->where(function ($builder) use ($plain, $typed, $morphPrefix): void {
                    if ($plain !== []) {
                        $builder->orWhereIn($morphPrefix . '_id', $plain);
                    }

                    foreach ($typed as $user) {
                        $builder->orWhere(function ($inner) use ($user, $morphPrefix): void {
                            $inner->where($morphPrefix . '_type', $user['type'])
                                ->where($morphPrefix . '_id', $user['id']);
                        });
                    }
                });
            }
        }

        if (! empty($filters['events'])) {
            $query->whereIn('event', $filters['events']);
        }

        if (! empty($filters['resource_types'])) {
            $query->whereIn('auditable_type', $filters['resource_types']);
        }

        if (! empty($filters['created_from']) && $createdAtColumn !== null) {
            $from = self::parseFilterDate($filters['created_from']);

            if ($from !== null) {
                $query->where($createdAtColumn, '>=', $from->startOfDay());
            }
        }

        if (! empty($filters['created_until']) && $createdAtColumn !== null) {
            // Inclusive of the WHOLE end day: strictly before the next day's
            // start. A `<= endOfDay()` binding is serialised without fractional
            // seconds, so an audit at 23:59:59.500 would fall outside it.
            $until = self::parseFilterDate($filters['created_until']);

            if ($until !== null) {
                $query->where($createdAtColumn, '<', $until->addDay()->startOfDay());
            }
        }

        // Order by the audit model's own created-at column; a model without a
        // timestamp column falls back to its primary key.
        return $query->latest($createdAtColumn ?? $audit->getKeyName())->cursorPaginate($perPage);
    }

    /**
     * The parsed date filter bound, or null when it cannot be parsed. The
     * Livewire form state is tamperable, so an unparsable value is ignored
     * instead of throwing a 500 (the filter simply does not apply). Both date
     * filters are day-granular: a time component is not applied (documented
     * in the README) — the date pickers send date-only strings.
     */
    private static function parseFilterDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * The resolver behind the user filter (`blackbox.users.resolver`).
     */
    public function userResolver(): UserResolver
    {
        $class = config('blackbox.users.resolver') ?: DefaultUserResolver::class;

        $resolver = is_string($class) && class_exists($class) ? app($class) : null;

        if (! $resolver instanceof UserResolver) {
            throw new InvalidArgumentException(sprintf(
                'The `blackbox.users.resolver` config value must be a class-string implementing [%s].',
                UserResolver::class,
            ));
        }

        return $resolver;
    }
}
