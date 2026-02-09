<?php

namespace App\Filament\Pages;

use Blackbox\FilamentBlackbox\FilamentBlackbox;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\WithPagination;

class Blackbox extends Page implements HasActions, HasSchemas
{
    use InteractsWithSchemas;
    use WithPagination;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::FingerPrint;

    protected static ?string $title = 'Black Box';

    // protected string $view = 'filament.pages.global-audit-log';
    protected static string $view = 'filament-blackbox::pages.blackbox';

    protected static ?int $navigationSort = 100;

    public $data;

    public int $perPage = 3;

    public function mount()
    {
        $this->form->fill($this->data);
    }

    public function updatingData(): void
    {
        $this->resetPage();
        $this->perPage = 10;
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->statePath('data')
            ->components([
                // 1. Get Users dynamically using the Auth provider's model
                Select::make('users')
                    ->options(function () {
                        $userModel = config('auth.providers.users.model');

                        return $userModel::all()->pluck('name', 'id'); // Use a standard 'name' or 'email'
                    })
                    ->multiple()
                    ->searchable()
                    ->placeholder('Select user...'),

                // 2. Pull Resource Types from your published config
                Select::make('resource_types')
                    ->options(
                        collect(config('blackbox.resources'))
                            ->forget('default')
                            ->mapWithKeys(fn ($item, $key) => [$key => $item['label'] ?? class_basename($key)])
                    )
                    ->multiple()
                    ->placeholder('Select type...'),

                // 3. Events stay static as they are defined by the Audit package
                Select::make('events')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'sync' => 'Sync',
                    ])
                    ->multiple()
                    ->placeholder('Select event...'),

                Group::make([
                    DatePicker::make('created_from')
                        ->label('From')
                        ->native(false)
                        ->placeholder('Start Date')
                        ->maxDate(fn (Get $get) => $get('created_until')),

                    DatePicker::make('created_until')
                        ->label('To')
                        ->native(false)
                        ->placeholder('End Date')
                        ->minDate(fn (Get $get) => $get('created_from')),
                ])->columns(2),
            ])->live();
    }

    public function getAudits()
    {
        // Simply call the service class
        return app(FilamentBlackbox::class)->getAudits(
            filters: $this->data ?? [],
            perPage: $this->perPage
        );
    }

    public function getBadgeColor($audit): string
    {
        // Look up config by the morph class (auditable_type)
        $config = config("blackbox.resources.{$audit->auditable_type}");

        // Return the specific color or the default from config
        return $config['color'] ?? config('blackbox.resources.default.color', 'gray');
    }

    public function getBadgeLabel($audit): string
    {
        $config = config("blackbox.resources.{$audit->auditable_type}");

        // Use the custom label from config, otherwise fallback to the class basename
        $label = $config['label'] ?? class_basename($audit->auditable_type);

        return $label . ' #' . $audit->auditable_id;
    }

    public function getBadgeUrl($audit): ?string
    {
        $config = config("blackbox.resources.{$audit->auditable_type}");

        // Dynamically generate the route based on Filament naming conventions
        if (isset($config['url'])) {
            return $config['url'];
        }

        return null;
    }

    protected function getViewData(): array
    {
        return [
            'audits' => $this->getAudits(),
        ];
    }
}
