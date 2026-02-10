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

After setting up a custom theme add the plugin's views to your theme css file or your app's css file if using the standalone packages.

```css
@source '../../../../vendor/redfieldchristabel/filament-blackbox/resources/**/*.blade.php';
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-blackbox-migrations"
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
return [
];
```

## Usage

```php
$filamentBlackbox = new Blackbox\FilamentBlackbox();
echo $filamentBlackbox->echoPhrase('Hello, Blackbox!');
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
