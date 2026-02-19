# A Military Grade Audit Package for Owen-it/lravel-audit.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/redfieldchristabel/filament-blackbox.svg?style=flat-square)](https://packagist.org/packages/redfieldchristabel/filament-blackbox)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/redfieldchristabel/filament-blackbox/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/redfieldchristabel/filament-blackbox/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/redfieldchristabel/filament-blackbox/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/redfieldchristabel/filament-blackbox/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/redfieldchristabel/filament-blackbox.svg?style=flat-square)](https://packagist.org/packages/redfieldchristabel/filament-blackbox)



A professional-grade auditing UI for Filament PHP. Transform raw laravel-auditing data into a powerful, centralized 'Blackbox' flight recorder with beautiful diffs, relationship-aware timelines, and advanced filtering.

## Installation

You can install the package via composer:

```bash
composer require redfieldchristabel/filament-blackbox
```

> [!IMPORTANT]
> If you have not set up a custom theme and are using Filament Panels follow the instructions in the [Filament Docs](https://filamentphp.com/docs/4.x/styling/overview#creating-a-custom-theme) first.

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
