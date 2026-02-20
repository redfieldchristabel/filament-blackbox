<?php

namespace Blackbox\FilamentBlackbox\Components;

use Livewire\Component;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Notifications\Notification;

new class extends Component implements HasActions, HasSchemas {
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Audit $audit;

    public ?Model $model;

    public function mount(Audit $audit)
    {
        $this->audit = $audit;
        
        $model = $audit->auditable;
        
        if (! $model && $audit->auditable_type && $audit->auditable_id && class_exists($audit->auditable_type)) {
            $modelClass = $audit->auditable_type;
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass))) {
                $model = $modelClass::withTrashed()->find($audit->auditable_id);
            }
        }
        
        $this->model = $model;
    }

    public function content(Schema $schema): Schema
    {

        // Compute diff for this audit
        $diff = [];
        $canView = $this->model && \blackboxCheck('view', $this->model);

        if ($canView) {
            foreach ($this->audit->getModified() as $attribute => $value) {
                $old = $value['field']['old'] ?? $value['old'] ?? null;
                $new = $value['field']['new'] ?? $value['new'] ?? null;

                if ($this->audit->event === 'sync') {
                    // For sync, we expect arrays.
                    // Decode if string
                    if (is_string($old) && str_starts_with($old, '[')) {
                        $decoded = json_decode($old, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $old = $decoded;
                        }
                    }
                    if (is_string($new) && str_starts_with($new, '[')) {
                        $decoded = json_decode($new, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $new = $decoded;
                        }
                    }

                    $old = is_array($old) ? $old : [];
                    $new = is_array($new) ? $new : [];

                    $added = $new;
                    $removed = $old;

                    // Optional: Render these values if need be.
                    // Usually sync is for IDs, but if we have a custom renderer for the attribute, utilize it.
                    $rendererMethod = $attribute . 'AuditRenderer';
                    if (method_exists($this->model, $rendererMethod)) {
                        $added = collect($added)->map(fn($item) => $this->model->$rendererMethod($item))->all();
                        $removed = collect($removed)->map(fn($item) => $this->model->$rendererMethod($item))->all();
                    }

                    $diff[] = [
                        'attribute' => $attribute,
                        'type' => 'sync',
                        'added' => $added,
                        'removed' => $removed,
                    ];

                } else {
                    // Standard update/create/delete
                    // Decode JSON if needed
                    if (is_string($old) && str_starts_with($old, '[')) {
                        $decoded = json_decode($old, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $old = $decoded;
                        }
                    }
                    if (is_string($new) && str_starts_with($new, '[')) {
                        $decoded = json_decode($new, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $new = $decoded;
                        }
                    }

                    // Smart renderer: check for <attribute>AuditRenderer method
                    $rendererMethod = $attribute . 'AuditRenderer';
                    if (method_exists($this->model, $rendererMethod)) {
                        $old = is_array($old)
                            ? collect($old)->map(fn($item) => $this->model->$rendererMethod($item))->implode(', ')
                            : $old;
                        $new = is_array($new)
                            ? collect($new)->map(fn($item) => $this->model->$rendererMethod($item))->implode(', ')
                            : $new;
                    }

                    $diff[] = [
                        'attribute' => $attribute,
                        'type' => $this->audit->event, // 'created', 'updated', etc.
                        'current' => $old,
                        'new' => $new,
                    ];
                }
            }
        }

        return $schema
            ->record($this->audit)
            ->components([

                Grid::make(4)
                    ->schema([
                        Group::make([
                            // Inside your components array:
                            ImageEntry::make('user_avatar')
                                ->hiddenLabel()
                                ->circular()
                                ->size(40)
                                ->state(function () {
                                    $user = $this->audit->user;

                                    if (!$user) {
                                        return null; // Will show default placeholder/initials
                                    }

                                    // 1. Check if the model has the Filament avatar method
                                    if (method_exists($user, 'getFilamentAvatarUrl')) {
                                        return $user->getFilamentAvatarUrl();
                                    }

                                    // 3. Let Filament handle the fallback (UI Avatars)
                                    return null;
                                })
                                ->defaultImageUrl(fn($state) => "https://ui-avatars.com/api/?name=" . urlencode($this->audit->user->name ?? 'S') . "&color=FFFFFF&background=111827"),
                            Group::make([
                                TextEntry::make('user')
                                    ->formatStateUsing(function ($state) {
                                        // $state is the User model (or null if no user)
                                        if (!$state) {
                                            return 'System'; // or whatever fallback you want for system/no-user actions
                                        }

                                        // Check if the method exists (safest way, no interface assumption needed)
                                        if (method_exists($state, 'getFilamentName')) {
                                            return $state->getFilamentName();
                                        }

                                        // Fallback to ->name attribute (common default in Filament & Laravel)
                                        return $state->name ?? 'Unknown User';
                                    })
                                    ->hiddenLabel()
                                    ->weight(FontWeight::Bold)
                                    ->placeholder('System'),
                                TextEntry::make('created_at')
                                    ->hiddenLabel()
                                    ->size(TextSize::Small)
                                    ->color('gray')
                                    ->dateTime(),
                            ]),
                        ])->columnSpan(2)->extraAttributes(['class' => 'flex items-center gap-3']),

                        TextEntry::make('event')
                            ->hiddenLabel()
                            ->badge()
                            // ->alignEnd()
                            ->color(fn(string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                'restored' => 'info',
                                'sync' => 'gray',
                                default => 'gray',
                        })
                            ->formatStateUsing(fn(string $state) => ucfirst($state)),

                        \Filament\Schemas\Components\Actions::make([
                            Action::make('rollback')
                                ->label('Revert')
                                ->icon(Heroicon::ArrowUturnLeft)
                                ->color('danger')
                                ->tooltip('Revert to the state before these changes')
                                ->requiresConfirmation()
                                ->visible(fn(): bool => $this->model && \blackboxCheck('update', $this->model) && $this->audit->event === 'updated')
                                ->modalContent(function () {
                                    $model = $this->model;
                                    if (!$model) {
                                        return null;
                                    }

                                    // Current state is in $model
                                    // Transition to old state (true for $old)
                                    $model->transitionTo($this->audit, true);

                                    $diff = [];
                                    foreach ($model->getDirty() as $attribute => $newValue) {
                                        $diff[] = [
                                            'attribute' => $attribute,
                                            'current' => $model->getOriginal($attribute), // This gets the value BEFORE the transition (since we haven't synced original)
                                            'new' => $newValue,
                                        ];
                                    }

                                    // Actually, transitionTo might sync original or not?
                                    // transitionTo sets attributes. getDirty() compares attributes to original.
                                    // $model was fresh from DB, so original is DB state.
                                    // attributes are updated state.
                        
                                    return view('filament-blackbox::components.audit-diff-modal', ['diff' => $diff]);
                                })
                                ->action(function () {

                                    $model = $this->model;

                                    if ($model) {
                                        $model->transitionTo($this->audit, true);

                                        $model->save();
                                        Notification::make()
                                            ->title('Reverted successfully')
                                            ->body('The model has been reverted successfully.')
                                            ->success()
                                            ->send();
                                    }
                                }),

                            Action::make('recover')
                                ->label('Recover')
                                ->icon(Heroicon::ArrowDownOnSquare)
                                ->color('danger')
                                ->tooltip('Restore the state after these changes')
                                ->requiresConfirmation()
                                ->visible(fn(): bool => $this->model && \blackboxCheck('update', $this->model) && $this->audit->event === 'updated')
                                ->modalContent(function () {
                                    $model = $this->model;
                                    if (!$model) {
                                        return null;
                                    }

                                    $model->transitionTo($this->audit);

                                    $diff = [];
                                    foreach ($model->getDirty() as $attribute => $newValue) {
                                        $diff[] = [
                                            'attribute' => $attribute,
                                            'current' => $model->getOriginal($attribute),
                                            'new' => $newValue,
                                        ];
                                    }

                                    return view('filament-blackbox::components.audit-diff-modal', ['diff' => $diff]);
                                })
                                ->action(function () {

                                    $model = $this->model;

                                    if ($model) {
                                        $model->transitionTo($this->audit);

                                        $model->save();
                                        Notification::make()
                                            ->title('Recover successfully')
                                            ->body('The model has been recovered successfully.')
                                            ->success()
                                            ->send();
                                    }
                                }),
                            Action::make('restore')
                                ->label('Restore')
                                ->icon(Heroicon::ArrowPath)
                                ->color('success')
                                ->tooltip('Restore this soft-deleted record')
                                ->requiresConfirmation()
                                ->visible(fn(): bool => 
                                    $this->model && 
                                    method_exists($this->model, 'trashed') && 
                                    $this->model->trashed() && 
                                    $this->audit->event === 'deleted' && 
                                    \blackboxCheck('restore', $this->model)
                                )
                                ->action(function () {
                                    if ($this->model && method_exists($this->model, 'restore')) {
                                        $this->model->restore();
                                        Notification::make()
                                            ->title('Restored successfully')
                                            ->body('The record has been restored successfully.')
                                            ->success()
                                            ->send();
                                    }
                                }),
                        ])->alignEnd(),
                    ]),

                ...collect($diff)->map(function ($item) {
                    $type = $item['type'] ?? 'updated';

                    // Skip deleted attributes if desired, or handle them
                    if ($type === 'deleted') {
                        return null;
                    }

                    return \Filament\Schemas\Components\View::make('filament-blackbox::components.audit-diffs.' . $type)
                        ->viewData([
                            'item' => $item,
                        ])
                        ->columnSpan('full');
                })->filter()->all(),

                TextEntry::make('hidden')
                    ->hiddenLabel()
                    ->state('RESTRICTED: INSUFFICIENT CLEARANCE')
                    ->badge()
                    ->color('danger')
                    ->visible(!$canView)
                    ->tooltip('You do not have permission to view this resource.'),

            ]);
    }

};

?>

<div>
    {{-- Let all your things have their places; let each part of your business have its time. - Benjamin Franklin --}}
    {{ $this->content }}

    <x-filament-actions::modals />
</div>