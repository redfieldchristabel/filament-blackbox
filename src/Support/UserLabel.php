<?php

namespace Blackbox\FilamentBlackbox\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The display label of an audit's user.
 *
 * The user is resolved the owen-it way (the audit's `user` morph, which honours
 * `audit.user.morph_prefix`), so no `users` table or `name` column is assumed.
 * The label is exactly Filament's own (`Filament::getUserName()`, i.e.
 * `HasName::getFilamentName()`, else the `name` attribute) — no extra
 * fallbacks of our own; only a bare `#id` when Filament yields nothing.
 */
final class UserLabel
{
    /**
     * Filament's user name (HasName / getFilamentName aware), else `#id`.
     */
    public static function forUser(Model $user): string
    {
        try {
            $name = Filament::getUserName($user);
        } catch (Throwable) {
            // Filament falls back to a `name` attribute, which the model may not have.
            $name = null;
        }

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return '#' . $user->getKey();
    }

    /**
     * 'System' only when the audit has no user; `#id` when it has a user that
     * can no longer be loaded.
     */
    public static function forAudit(Model $audit): string
    {
        $morphPrefix = (string) config('audit.user.morph_prefix', 'user');

        $id = $audit->getAttribute($morphPrefix . '_id');

        if ($id === null || $id === '') {
            return 'System';
        }

        try {
            $user = $audit->getRelationValue('user');
        } catch (Throwable) {
            $user = null;
        }

        return $user instanceof Model ? self::forUser($user) : '#' . $id;
    }
}
