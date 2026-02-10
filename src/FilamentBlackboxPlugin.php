<?php

namespace Blackbox\FilamentBlackbox;

use Blackbox\FilamentBlackbox\Pages\Blackbox;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentBlackboxPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-blackbox';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            Blackbox::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
