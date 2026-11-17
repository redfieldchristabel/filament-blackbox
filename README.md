# A Military Grade Audit Package for Owen-it/lravel-audit.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/redfieldchristabel/filament-blackbox.svg?style=flat-square)](https://packagist.org/packages/redfieldchristabel/filament-blackbox)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/redfieldchristabel/filament-blackbox/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/redfieldchristabel/filament-blackbox/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/redfieldchristabel/filament-blackbox/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/redfieldchristabel/filament-blackbox/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/redfieldchristabel/filament-blackbox.svg?style=flat-square)](https://packagist.org/packages/redfieldchristabel/filament-blackbox)



A professional-grade auditing UI for Filament PHP. Transform raw laravel-auditing data into a powerful, centralized 'Blackbox' flight recorder with beautiful diffs, relationship-aware timelines, and advanced filtering.

Supports Filament 5.11 and later releases within the 5.x major (`filament/filament: ^5.11`).

## Installation

You can install the package via composer:

```bash
composer require redfieldchristabel/filament-blackbox
```

> [!IMPORTANT]
> If you have not set up a custom theme and are using Filament Panels follow the instructions in the [Filament Docs](https://filamentphp.com/docs/5.x/styling/overview#creating-a-custom-theme) first.

After setting up a custom theme add the plugin's views to your theme css file or your app's css file if using the standalone packages. This is the recommended way to use the package and the default configuration.

```css
@source '../../../../vendor/redfieldchristabel/filament-blackbox/**/*';
```

If you prefer to have the package register the assets for you, you can publish the config file and set `register_assets` to `true`.

### Database Setup

This package relies on [OwenIt/laravel-auditing](https://www.laravel-auditing.com/guide/installation.html). Please follow their official guide to install and configure it first.

If you haven't yet exposed the migrations for the audit package, you can do so with:

```bash
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider" --tag="migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-blackbox-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="filament-blackbox-views"
```

This is the contents of the published config file:

```php
/**
 * Filament Blackbox Configuration
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Resource Mapping
    |--------------------------------------------------------------------------
    |
    | This maps your Auditable models to their UI badges, routes, and relationships.
    |
    | Model Mapping:
    | The key should be the fully qualified class name of your model.
    |
    | 'badge_class':
    | These classes are APPENDED to the base Filament badge classes:
    | "fi-badge fi-color-custom fi-size-sm fi-badge-color-..."
    | You can use Filament colors (e.g., 'fi-color-primary') or standard Tailwind.
    |
    | IMPORTANT FOR TAILWIND:
    | If you use custom Tailwind classes here, you MUST add the following to
    | your theme's CSS file so Tailwind's JIT compiler scans this config:
    | @source "../../config/blackbox.php";
    |
    | 'resource':
    | The fully qualified name of the Filament Resource class. Used to generate
    | 'edit' or 'view' URLs for the auditable record.
    |
    | 'relations':
    | Defines which related models should have their audits pulled into the
    | main record's timeline (e.g., seeing "Address" edits while viewing a "User").
    |
    | KEY: The relationship name defined on your Model (e.g., 'profile').
    | VALUE: A comma-separated string of nested relationships to eager load
    |        (e.g., 'user,avatar'). If no sub-relations are needed, leave EMPTY ('').
    |
    */
    'resources' => [

        // \App\Models\User::class => [
        //     'label' => 'User',
        //     'badge_class' => 'fi-color-primary', // Appends to base badge classes
        //     'resource' => \App\Filament\Resources\UserResource::class,
        //     'relations' => [
        //         'profile' => '', // Loads profile audits; no sub-relations
        //         'posts'   => 'comments,author', // Loads posts and eager-loads comments/author
        //     ]
        // ],

        /*
        |--------------------------------------------------------------------------
        | Default Fallback Settings
        |--------------------------------------------------------------------------
        */
        'default' => [
            'badge_class' => 'fi-color-gray',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Registration
    |--------------------------------------------------------------------------
    |
    | If true, the package's CSS assets will be automatically registered with Filament.
    |
    | Recommended: Set to false and include the following in your theme's CSS file:
    |
    */
    // @source '../../../../vendor/redfieldchristabel/filament-blackbox/**/*';
    // @source '../../../../config/blackbox.php';

    'register_assets' => false,
];

```

## Usage

```php
// In a Filament Panel Provider
$panel
    ->plugin(Blackbox\FilamentBlackbox\FilamentBlackboxPlugin::make())
```

## Screenshots

![Black Box Overview](images/Screenshot%202026-03-30%20at%2010-03-28%20Black%20Box%20-%20SISPAA.png)
![User Audit List](images/Screenshot%202026-03-30%20at%2010-04-27%20Users%20-%20SISPAA.png)
![User Audit Detail](images/Screenshot%202026-03-30%20at%2010-05-00%20Users%20-%20SISPAA.png)
![Black Box Timeline](images/Screenshot%202026-03-30%20at%2010-05-16%20Black%20Box%20-%20SISPAA.png)


### Soft Deletes & Recovery

Filament Blackbox has built-in, out-of-the-box support for models utilizing Laravel's `SoftDeletes` trait! 

When a model is soft-deleted, Blackbox will automatically resolve the trashed record so you can still view its full audit history—no more "Missing Record" errors. 

Even better, when viewing the audit log of a `deleted` event for a soft-deleted model, Blackbox provides a native **Restore** action button. This allows administrators with the proper permissions to recover accidentally deleted records with a single click directly from the audit timeline!

### Customizing Audit Rendering

You can customize how specific attributes are rendered in the audit timeline by adding "magic methods" to your Auditable models. This is particularly useful for ID-based fields (like foreign keys) or complex JSON structures.

Define a method following the pattern `{attribute}AuditRenderer` on your model. This method will receive the raw value (from either the `old` or `new` batch) and should return a string representation.

```php
namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

class Order extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /**
     * Custom renderer for the 'status_id' attribute.
     * Works for standard events (created, updated) and 'sync' events.
     */
    public function statusIdAuditRenderer($value): string
    {
        return match($value) {
            1 => 'Pending',
            2 => 'Processing',
            3 => 'Completed',
            default => "Unknown ($value)",
        };
    }
}
```

When Blackbox encounters the `status_id` attribute in an audit log, it will automatically use this method to transform the ID into its human-readable label.

#### Relation Morphing (Sync Events)

This feature is also powerful for `sync` events (commonly used for Many-to-Many relationships like Roles or Tags). When a relation is synced, the audit data often contains full objects. You can "morph" these into simple strings for the UI:

```php
class User extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /**
     * Morph the 'roles' sync data to show only the role names.
     * The $item parameter will be an array representing the role object.
     */
    public function rolesAuditRenderer(array $item): string
    {
        return $item['name'] ?? 'Unknown Role';
    }
}
```

With this method, the Blackbox UI will show a clean list of role names (e.g., "Admin", "Editor") instead of dumping the raw JSON objects of the roles.

## Built on owen-it/laravel-auditing

Blackbox is a UI on top of [owen-it/laravel-auditing](https://www.laravel-auditing.com) `^14`. It reads audits through owen-it's `Audit` model (`config('audit.implementation')`) and applies Revert/Recover through owen-it's own `transitionTo()`. It does not keep a parallel configuration for anything owen-it already configures.

### Exclusions come from your model

What Blackbox shows, reverts and recovers is decided by your auditable model's current owen-it configuration: `auditExclude` / `audit.exclude`, `auditInclude`, `auditStrict` / `audit.strict` (hidden and non visible attributes), `auditTimestamps`, plus `$hidden` / `$visible` in strict mode — including owen-it's non scalar exclusions (`audit.allowed_array_values`, non stringable objects).

**Non-strict models follow owen-it exactly: `$hidden` only counts in strict mode.** Blackbox deliberately adds no stricter `$hidden` rule, so on a model without `auditStrict` a hidden attribute still shows up in the timeline and can be restored — exactly what owen-it's own `resolveAuditExclusions()` does. If a non-strict model must never show or restore a field (`password`, `remember_token`, …), put it in `$auditExclude` (or the global `audit.exclude`) instead of `$hidden`: excluded fields are hidden and never restored from old rows too.

These rules are applied to **old rows too**. If you exclude `password` or `remember_token` today, audits written before that still hold the value, but Blackbox never shows them and Revert/Recover never restores them. This also covers audit keys that are no longer on the model at all: in strict mode with a `$visible` allowlist, only allowlisted attributes are ever shown or restored, even when the row carries a key that is missing from the hydrated model (a dropped column or a `select()` subset). Excluded legacy values are also never formatted through the model's newer casts or accessors, so a plain-text value under a newer `encrypted` cast cannot break the timeline. Revert and Recover are only offered for `updated` audits that still have at least one auditable attribute to apply. Recover/Revert always go through a filtered copy of the audit, so owen-it's own checks (and `AuditableTransitionException`) still apply.

One more guard on top: after applying an audit, every dirty attribute is re-checked. A setter for an allowed field can also change an excluded one — that change is refused (`Blackbox\FilamentBlackbox\Exceptions\ProtectedAttributeTransitionException`, which extends owen-it's `AuditableTransitionException`), the model is reset, and nothing is saved. The preview and the notification only say "This change can't be reverted because it would modify protected fields." When owen-it itself refuses a transition (`AuditableTransitionException`), the UI shows the generic "This audit can't be applied to the current record." and the real message — which can name attributes — goes to the log.

### Audit implementations

Any Eloquent model implementing owen-it's `Audit` contract works as `config('audit.implementation')` — it does not have to extend owen-it's default `Audit` model. Blackbox reads, renders and transitions it through the contract, including implementations with renamed timestamp columns: the page, the slide-over, the `created_from`/`created_until` filters and the ordering all use the model's `getCreatedAtColumn()` (and degrade gracefully when a model reports no timestamp column at all). The date filters are day-granular: both bounds cover whole days (an audit at 00:00 or 23:59:59.500 on the chosen day is included), a time component in a filter value is not applied, and an unparsable value is ignored rather than erroring.

### Users

The audit user is resolved the owen-it way (the audit's `user` morph, `audit.user.morph_prefix`) and displayed the Filament way. Names come from `Filament::getUserName()` — the `Filament\Models\Contracts\HasName` contract (`getFilamentName()`, else the plain `name` attribute), with a bare `#id` only when that yields nothing. Avatars come from `Filament::getUserAvatarUrl()` — the `Filament\Models\Contracts\HasAvatar` contract (`getFilamentAvatarUrl()`, else `avatar_url`, else the panel's default avatar provider); the package never points at an external avatar service itself. Audits without a user show "System" and no avatar. Implement `HasName` / `HasAvatar` on your user model to control both (a composite name such as `first_name . ' ' . 'last_name'` is a typical `getFilamentName()`).

Because `getFilamentName()` is PHP-only — it may compose several columns and cannot be reproduced in SQL — the user filter's `blackbox.users.search_columns` must list REAL database columns, or the filter finds nothing. Columns a table does not have are skipped. For a composite-name model: `['first_name', 'last_name', 'email']`.

The user filter is searched server-side and never loads every user. The default resolver offers the user model of EVERY guard in `audit.user.guards` (laravel-auditing's own setting), each resolved through `auth.guards.<guard>.provider` to `auth.providers.<name>.model`, so a differently named provider works and several guards with different user models are all searchable. Each filter option is keyed by the audit's own user morph as owen-it stores it (`<user_type>:<user_id>`, under `audit.user.morph_prefix`), so two users with the same id on different models stay distinguishable in the options and in the filtered audit list. The search runs over `blackbox.users.search_columns` (default `['name', 'email']`; columns a table does not have are skipped — the table's columns are listed once per model per search), escapes `LIKE` wildcards so a `%` or `_` in the term matches literally, matches case-insensitively on every database driver (lowered on both sides of the comparison, since plain `like` is case-sensitive on PostgreSQL), and returns the options ordered by key. When no configured column exists on the table, only an exact key lookup makes sense and a non-numeric term finds nothing. To search differently (aliases, tenants, another model), point `blackbox.users.resolver` at a class implementing `Blackbox\FilamentBlackbox\Contracts\UserResolver`:

```php
// config/blackbox.php
'users' => [
    'resolver' => App\Support\BlackboxUserResolver::class, // class-string only, so config:cache works
    'search_columns' => ['name', 'email'],
],
```

```php
use Blackbox\FilamentBlackbox\Contracts\UserResolver;

class BlackboxUserResolver implements UserResolver
{
    /** @return array<int|string, string> [id => label] */
    public function search(string $search): array { /* ... */ }

    /** @return array<int|string, string> [id => label] */
    public function labels(array $ids): array { /* ... */ }
}
```

### Authorization without a policy

Blackbox checks the model's policy (`view`, `update`, `restore`). For a model with **no** policy, `blackbox.authorize_without_policy` decides: `true` (default, the behaviour of v0.0.x) allows it, `false` denies it. Models that have a policy always go through the policy.

### Relation timelines

List the related models whose audits belong in a record's timeline in `blackbox.resources.<Model>.relations`, either associative (`['posts' => 'comments,author']`) or indexed (`['profile', 'posts']`). When that key is absent, Blackbox calls an `auditRelations()` method on the model if one is callable and uses the same array shape (an inaccessible, e.g. protected, method is treated as absent):

```php
public function auditRelations(): array
{
    return ['profile', 'posts' => 'comments,author'];
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [redfieldchristabel](https://github.com/redfieldchristabel)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
