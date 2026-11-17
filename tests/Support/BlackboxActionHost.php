<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Blackbox\FilamentBlackbox\Actions\BlackboxAction;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * Minimal Livewire host that exposes the Blackbox action for one record.
 */
class BlackboxActionHost extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Model $record;

    public function blackboxAction(): Action
    {
        return BlackboxAction::make('blackbox')->record($this->record);
    }

    public function render(): string
    {
        return <<<'HTML'
            <div>
                {{ $this->blackboxAction }}

                <x-filament-actions::modals />
            </div>
            HTML;
    }
}
