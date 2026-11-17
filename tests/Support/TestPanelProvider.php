<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Blackbox\FilamentBlackbox\FilamentBlackboxPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilamentBlackboxPlugin::make());
    }
}
