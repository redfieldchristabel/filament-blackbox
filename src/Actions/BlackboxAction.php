<?php

namespace Blackbox\FilamentBlackbox\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class BlackboxAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'Blackbox';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->slideOver();

        $this->visible(fn(Model $record): bool => blackboxCheck('view', $record));

        // $this->modalContent(fn (Model $record) => view('filament.actions.audit-timeline', [
        //     'record' => $record,
        // ]));

        $this->modalContent(fn(Model $record) => new HtmlString(
            \Livewire\Livewire::mount('blackbox::blackbox-slideover', ['record' => $record])
        ));

        $this->label('Blackbox');
        $this->icon(Heroicon::Clock);
        $this->modalSubmitAction(false);
        $this->modalCancelAction(false);
        $this->modalAutofocus(false);

        $this->tooltip('saya tooltip');
    }
}
