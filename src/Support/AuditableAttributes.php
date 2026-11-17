<?php

namespace Blackbox\FilamentBlackbox\Support;

use Blackbox\FilamentBlackbox\Exceptions\ProtectedAttributeTransitionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Audit as AuditContract;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Exceptions\AuditableTransitionException;
use UnitEnum;

/**
 * Applies an auditable model's CURRENT owen-it/laravel-auditing rules to the
 * values stored in (possibly old) audit rows.
 *
 * A row written before a field was excluded still carries that field's value,
 * so the timeline and Revert/Recover must re-check every attribute against the
 * model's own rules instead of trusting the row. Nothing here defines rules of
 * its own: they come from the model's owen-it configuration.
 *
 * The rule set mirrors owen-it's `OwenIt\Auditing\Auditable::resolveAuditExclusions()`
 * (vendor/owen-it/laravel-auditing/src/Auditable.php, protected) and
 * `isAttributeAuditable()` (protected), built only from public model API:
 * getAuditExclude(), getAuditInclude(), getAuditStrict(), getAuditTimestamps(),
 * getHidden(), getVisible() and getAttributes(). owen-it exposes no public way
 * to ask the question, so it is mirrored here, in this one class.
 *
 * Two places are deliberately STRICTER than the engine, for safety:
 * - In strict mode with a non-empty `$visible` allowlist, an attribute is
 *   auditable only if it is IN the allowlist — checked against the allowlist
 *   itself, not against the keys currently on the model, so an audit key that
 *   is absent from the hydrated model (removed column, select() subset,
 *   legacy key) is rejected too. owen-it's own visible diff can only name
 *   keys that are on the model, and its transitionTo() would refuse such a
 *   key anyway (attribute incompatibility).
 * - After `transitionTo()`, every dirty attribute is re-checked: a setter for
 *   an allowed field can dirty an excluded one, and that change is never
 *   shown or saved (see transition()).
 *
 * Revert/Recover go through owen-it's own `transitionTo()` with a filtered COPY
 * of the audit, so its checks and `AuditableTransitionException` still apply.
 */
final class AuditableAttributes
{
    /**
     * The audit model's created-at column, null-safe: null when the model
     * reports no timestamp column at all (`const CREATED_AT = null`).
     */
    public static function auditCreatedAtColumn(Model $audit): ?string
    {
        $column = $audit->getCreatedAtColumn();

        return is_string($column) && $column !== '' ? $column : null;
    }

    /**
     * Attributes the model's current rules keep OUT of its audits. The strict
     * mode visible allowlist is not enumerable (it excludes everything not in
     * the list), so it is enforced per attribute in {@see isAuditable()}.
     *
     * The rules are computed FRESH on every call — never memoised: a
     * fingerprint of the model's state would have to serialise every
     * attribute value (which throws on Closures) on every single lookup,
     * making the memo more expensive than the rules it caches. Callers that
     * ask per attribute must compute once themselves — {@see filter()} does.
     *
     * @return array<int, string>
     */
    public static function excluded(Model $model): array
    {
        if (! $model instanceof AuditableContract) {
            return [];
        }

        return self::computeExcluded($model);
    }

    /**
     * The exclusion rules themselves, mirrored from owen-it (see the class
     * docblock).
     *
     * @return array<int, string>
     */
    private static function computeExcluded(AuditableContract & Model $model): array
    {
        $excluded = $model->getAuditExclude();

        // Strict mode: hidden attributes are excluded. The non visible ones are
        // enforced per attribute in isAuditable() against the allowlist itself.
        if ($model->getAuditStrict()) {
            $excluded = array_merge($excluded, $model->getHidden());
        }

        // Timestamps are only audited when the model opts in.
        if (! $model->getAuditTimestamps()) {
            if ($model->getCreatedAtColumn()) {
                $excluded[] = $model->getCreatedAtColumn();
            }

            if ($model->getUpdatedAtColumn()) {
                $excluded[] = $model->getUpdatedAtColumn();
            }

            if (in_array(SoftDeletes::class, class_uses_recursive($model), true) && method_exists($model, 'getDeletedAtColumn')) {
                $excluded[] = $model->getDeletedAtColumn();
            }
        }

        // Apart from null, non scalar CURRENT values will be excluded — the
        // last block of owen-it's resolveAuditExclusions(), including its
        // `audit.allowed_array_values` setting.
        $attributes = Arr::except($model->getAttributes(), $excluded);

        foreach ($attributes as $attribute => $value) {
            if (
                (is_array($value) && ! config('audit.allowed_array_values', false)) ||
                (is_object($value) && ! method_exists($value, '__toString') && ! ($value instanceof UnitEnum))
            ) {
                $excluded[] = $attribute;
            }
        }

        return array_values(array_unique(array_map('strval', $excluded)));
    }

    /**
     * Whether the attribute is auditable under the model's current rules.
     * Models that are not owen-it auditables are not restricted.
     */
    public static function isAuditable(Model $model, string $attribute): bool
    {
        if (! $model instanceof AuditableContract) {
            return true;
        }

        return self::isAuditableAgainst($model, self::excluded($model), $attribute);
    }

    /**
     * The per-attribute rules {@see isAuditable()} applies, shared with
     * {@see filter()} so both answer exactly the same question against one
     * precomputed exclusion list.
     */
    private static function isAuditableAgainst(AuditableContract & Model $model, array $excluded, string $attribute): bool
    {
        if (in_array($attribute, $excluded, true)) {
            return false;
        }

        // Strict mode with a visible allowlist: ONLY visible attributes are
        // auditable. Checked against the allowlist itself — not against the
        // keys currently on the model — so audit keys absent from the
        // hydrated model (removed column, select() subset, legacy key) are
        // rejected too.
        if ($model->getAuditStrict() && ($visible = $model->getVisible()) !== [] && ! in_array($attribute, $visible, true)) {
            return false;
        }

        // Auditable when explicitly listed, or when the include list is empty.
        $include = $model->getAuditInclude();

        return empty($include) || in_array($attribute, $include, true);
    }

    /**
     * Drop every key the model's current rules do not allow.
     *
     * The exclusion rules are computed ONCE per call and reused for every
     * key — one rule set per filter, however many attributes it screens.
     *
     * @param  array<string, mixed>  $values  attribute => anything
     * @return array<string, mixed>
     */
    public static function filter(Model $model, array $values): array
    {
        if (! $model instanceof AuditableContract) {
            return $values;
        }

        $excluded = self::excluded($model);

        return array_filter(
            $values,
            fn (string | int $attribute): bool => self::isAuditableAgainst($model, $excluded, (string) $attribute),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * The audit's `getModified()` data (attribute => ['old' => ..., 'new' => ...]),
     * reduced to the attributes the model's current rules still allow.
     *
     * The values are filtered BEFORE `getModified()` runs: it formats every
     * historical value through the auditable's casts and accessors, so an
     * excluded legacy value (e.g. plain text under a newer encrypted cast)
     * would throw and break the whole timeline. The formatting happens on a
     * fresh replica that only holds the still-auditable values.
     *
     * @return array<string, mixed>
     */
    public static function modified(Model $model, Model & AuditContract $audit): array
    {
        $copy = self::filteredCopy($model, $audit);

        // Reuse the original row's loaded relations, so the replica does not
        // re-query `user` / `auditable` for every rendered audit.
        foreach (['user', 'auditable'] as $relation) {
            if ($audit->relationLoaded($relation)) {
                $copy->setRelation($relation, $audit->getRelation($relation));
            }
        }

        /** @var array<string, mixed> $modified */
        $modified = $copy->getModified();

        return $modified;
    }

    /**
     * The audit's RAW stored `old_values` / `new_values` that the model's
     * current rules still allow.
     *
     * Raw on purpose: `getModified()` runs values through the model's casts and
     * accessors, and owen-it does that itself again in `transitionTo()`.
     * Filtering only drops keys, so a model with nothing excluded transitions
     * exactly as it would without this class.
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public static function transitionableValues(Model $model, Model & AuditContract $audit): array
    {
        $old = $audit->getAttribute('old_values');
        $new = $audit->getAttribute('new_values');

        return [
            'old' => self::filter($model, is_array($old) ? $old : []),
            'new' => self::filter($model, is_array($new) ? $new : []),
        ];
    }

    /**
     * Whether applying the audit would change anything: at least one
     * still-auditable attribute carries the requested state.
     */
    public static function canTransition(Model $model, Model & AuditContract $audit, bool $old): bool
    {
        return self::transitionableValues($model, $audit)[$old ? 'old' : 'new'] !== [];
    }

    /**
     * Dirty attributes of an already transitioned model that the model's
     * CURRENT rules do not allow: a setter for an allowed field touched an
     * excluded one. These must never be shown or saved.
     *
     * @return array<int, string>
     */
    public static function protectedDirtyAttributes(Model $model): array
    {
        return array_values(array_filter(
            array_keys($model->getDirty()),
            fn (string $attribute): bool => ! self::isAuditable($model, $attribute),
        ));
    }

    /**
     * An unsaved replica of the audit (same auditable type and id, same
     * metadata) holding only the values the model's current rules allow.
     */
    public static function filteredCopy(Model $model, Model & AuditContract $audit): Model & AuditContract
    {
        $values = self::transitionableValues($model, $audit);

        $copy = $audit->replicate();

        // replicate() drops the audit model's own timestamp columns, but
        // Audit::resolveData() serialises them (`audit_created_at` /
        // `audit_updated_at`), so carry the values over under the columns the
        // audit model actually uses — null-safe for a model that reports no
        // timestamp column at all (timestamps off).
        $createdAt = $audit->getCreatedAtColumn();
        $updatedAt = $audit->getUpdatedAtColumn();

        if ($createdAt !== null) {
            $copy->setAttribute($createdAt, $audit->getAttribute($createdAt));
        }

        if ($updatedAt !== null && $updatedAt !== $createdAt) {
            $copy->setAttribute($updatedAt, $audit->getAttribute($updatedAt));
        }

        $copy->setAttribute('old_values', $values['old']);
        $copy->setAttribute('new_values', $values['new']);

        return $copy;
    }

    /**
     * Apply the audit to the model (the old state when `$old`, else the new
     * state) through owen-it's `transitionTo()`, using a filtered copy so a row
     * written before an exclusion can never restore an excluded field.
     *
     * After the transition, every dirty attribute is re-checked: a setter for
     * an allowed field can dirty an excluded one. When that happens the model
     * is reset to its original state and
     * {@see ProtectedAttributeTransitionException} is thrown — the caller must
     * not save.
     *
     * @throws AuditableTransitionException
     * @throws ProtectedAttributeTransitionException
     */
    public static function transition(Model $model, Model & AuditContract $audit, bool $old = false): void
    {
        if (! $model instanceof AuditableContract) {
            throw new InvalidArgumentException('The model must be an owen-it auditable.');
        }

        $model->transitionTo(self::filteredCopy($model, $audit), $old);

        $protected = self::protectedDirtyAttributes($model);

        if ($protected !== []) {
            // A setter on an allowed field touched an excluded one: drop every
            // in-memory change so nothing can be saved afterwards. Available
            // since Laravel 9.29 (laravel/framework#43772) — safely above the
            // floor this package's dependencies allow, as `filament/filament`
            // ^5.11 already requires laravel/framework ^11.44.
            $model->discardChanges();

            throw new ProtectedAttributeTransitionException(sprintf(
                'Applying this audit would also modify the protected attribute(s) [%s], so it was refused and nothing was saved.',
                implode(', ', $protected),
            ), $protected);
        }
    }
}
