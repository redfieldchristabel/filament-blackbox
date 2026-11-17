<?php

namespace Blackbox\FilamentBlackbox\Components;

use Livewire\Component;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Notifications\Notification;
use Blackbox\FilamentBlackbox\Support\AuditableAttributes;
use Blackbox\FilamentBlackbox\Support\UserLabel;
use Blackbox\FilamentBlackbox\Exceptions\ProtectedAttributeTransitionException;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Contracts\Audit as AuditContract;
use OwenIt\Auditing\Exceptions\AuditableTransitionException;

new class extends Component implements HasActions, HasSchemas {
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * The only refusal detail the UI shows when applying the audit would
     * touch a protected (excluded/hidden) attribute: no attribute names, no
     * values — those stay on the exception for logs.
     */
    protected const PROTECTED_TRANSITION_MESSAGE = "This change can't be reverted because it would modify protected fields.";

    /**
     * The only refusal detail the UI shows when owen-it itself refuses the
     * transition (type/id mismatch, attribute incompatibilities, redactor):
     * the real message may name attributes, so it goes to the log only.
     */
    protected const REFUSED_TRANSITION_MESSAGE = "This audit can't be applied to the current record.";

    /**
     * The only refusal detail the UI shows when the server-side re-check
     * refuses the action (ability, event, record state): the reason is not
     * disclosed — only that the action is no longer available.
     */
    protected const UNAVAILABLE_ACTION_MESSAGE = 'This action is no longer available for this record.';

    /**
     * Log an owen-it refusal with everything a human needs; nothing here
     * reaches the UI.
     */
    protected function logRefusedTransition(AuditableTransitionException $exception): void
    {
        Log::warning('Blackbox: audit transition refused — ' . $exception->getMessage(), [
            'incompatibilities' => $exception->getIncompatibilities(),
            'audit_id' => $this->audit->getKey(),
            'auditable_type' => $this->audit->getAttribute('auditable_type'),
            'auditable_id' => $this->audit->getAttribute('auditable_id'),
        ]);
    }

    /**
     * The configured `audit.implementation` model — any Eloquent model
     * implementing owen-it's Audit contract, not only the default model.
     *
     * @var Model&AuditContract
     */
    public Model $audit;

    public ?Model $model;

    public function mount(Model $audit)
    {
        if (! $audit instanceof AuditContract) {
            throw new \InvalidArgumentException(sprintf(
                'The audit must be an Eloquent model implementing [%s] (the configured `audit.implementation`), got [%s].',
                AuditContract::class,
                $audit::class,
            ));
        }

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

    /**
     * The server-side re-check behind Revert / Recover. The action's
     * `visible()` is never trusted on its own: a direct call to
     * applyTransition() / previewTransition() must be refused the same way.
     * Checked here, once, right before anything is previewed or saved:
     * - the auditable record still exists,
     * - the user may still update it (blackboxCheck against the model's policy),
     * - the audit is an `updated` one,
     * - Recover is additionally refused while the record is soft-deleted —
     *   coming back is the Restore action's job,
     * - at least one auditable attribute would still change.
     */
    protected function canTransition(bool $old): bool
    {
        $model = $this->model;

        if (! $model) {
            return false;
        }

        if ($this->audit->event !== 'updated') {
            return false;
        }

        if (! $old && method_exists($model, 'trashed') && $model->trashed()) {
            return false;
        }

        return \blackboxCheck('update', $model)
            && AuditableAttributes::canTransition($model, $this->audit, $old);
    }

    /**
     * Preview of what Revert/Recover would change, computed on a throwaway copy of the
     * model through owen-it's transitionTo() with a filtered copy of the audit.
     */
    protected function previewTransition(bool $old)
    {
        // Server-side re-check: the action's visible() is not trusted here.
        if (! $this->canTransition($old)) {
            return null;
        }

        $model = clone $this->model;

        try {
            AuditableAttributes::transition($model, $this->audit, $old);
        } catch (ProtectedAttributeTransitionException $exception) {
            // A generic refusal only: the protected attribute names and values
            // stay on the exception for logs, never in the UI.
            $this->logRefusedTransition($exception);

            return view('filament-blackbox::components.audit-diff-modal', [
                'diff' => [],
                'error' => self::PROTECTED_TRANSITION_MESSAGE,
            ]);
        } catch (AuditableTransitionException $exception) {
            // owen-it's own message may name attributes — log it, show only
            // the generic refusal.
            $this->logRefusedTransition($exception);

            return view('filament-blackbox::components.audit-diff-modal', [
                'diff' => [],
                'error' => self::REFUSED_TRANSITION_MESSAGE,
            ]);
        }

        $diff = [];

        foreach ($model->getDirty() as $attribute => $newValue) {
            // A setter on an allowed field can dirty an excluded one (transition()
            // refuses those), and the preview must never render an excluded dirty
            // key either way.
            if (! AuditableAttributes::isAuditable($model, $attribute)) {
                continue;
            }

            $diff[] = [
                'attribute' => $attribute,
                // The model came fresh from the database, so the original is the current state.
                'current' => $model->getOriginal($attribute),
                'new' => $newValue,
            ];
        }

        return view('filament-blackbox::components.audit-diff-modal', ['diff' => $diff]);
    }

    protected function applyTransition(bool $old, string $title, string $body): void
    {
        // Server-side re-check: the action's visible() is not trusted here.
        // Unauthorised users, non-updated audits, a soft-deleted record on
        // Recover and fully excluded transitions are refused before anything
        // is saved — with a refusal notification, so a click that can no
        // longer do anything (e.g. the permission changed after the page
        // rendered) is never silent.
        if (! $this->canTransition($old)) {
            Notification::make()
                ->title('Action not available')
                ->body(self::UNAVAILABLE_ACTION_MESSAGE)
                ->danger()
                ->send();

            return;
        }

        $model = $this->model;

        try {
            AuditableAttributes::transition($model, $this->audit, $old);
        } catch (ProtectedAttributeTransitionException $exception) {
            // Same generic refusal as the preview: no protected attribute
            // names or values reach the notification either.
            $this->logRefusedTransition($exception);

            Notification::make()
                ->title('Could not apply this audit')
                ->body(self::PROTECTED_TRANSITION_MESSAGE)
                ->danger()
                ->send();

            return;
        } catch (AuditableTransitionException $exception) {
            // Generic refusal only: owen-it's message (and any attribute
            // names in it) goes to the log.
            $this->logRefusedTransition($exception);

            Notification::make()
                ->title('Could not apply this audit')
                ->body(self::REFUSED_TRANSITION_MESSAGE)
                ->danger()
                ->send();

            return;
        }

        $model->save();

        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->send();
    }

    /**
     * Restore of a soft-deleted record, re-checked server-side: the action's
     * `visible()` is not trusted here either, so a direct call is refused the
     * same way. Restoring needs the record still soft-deleted, the audit to
     * be its `deleted` one, and the user still allowed to restore.
     */
    protected function applyRestore(): void
    {
        $model = $this->model;

        if (
            ! $model
            || ! method_exists($model, 'trashed')
            || ! method_exists($model, 'restore')
            || ! $model->trashed()
            || $this->audit->event !== 'deleted'
            || ! \blackboxCheck('restore', $model)
        ) {
            // Same refusal feedback as Revert / Recover: never silent.
            Notification::make()
                ->title('Action not available')
                ->body(self::UNAVAILABLE_ACTION_MESSAGE)
                ->danger()
                ->send();

            return;
        }

        $model->restore();

        Notification::make()
            ->title('Restored successfully')
            ->body('The record has been restored successfully.')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {

        // Compute diff for this audit
        $diff = [];
        $canView = $this->model && \blackboxCheck('view', $this->model);

        if ($canView) {
            // Only what the model's CURRENT owen-it rules still allow: rows written before a
            // field was excluded (password, remember_token, hidden fields) still hold its value.
            foreach (AuditableAttributes::modified($this->model, $this->audit) as $attribute => $value) {
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
                                ->state(function (): ?string {
                                    $user = $this->audit->user;

                                    // A system audit has no user: no avatar at all.
                                    if (! $user instanceof Model) {
                                        return null;
                                    }

                                    // Filament's own resolution: HasAvatar::
                                    // getFilamentAvatarUrl(), else `avatar_url`,
                                    // else the panel's default avatar provider.
                                    return Filament::getUserAvatarUrl($user);
                                }),
                            Group::make([
                                TextEntry::make('user')
                                    ->state(fn () => UserLabel::forAudit($this->audit))
                                    ->hiddenLabel()
                                    ->weight(FontWeight::Bold)
                                    ->placeholder('System'),
                                TextEntry::make(AuditableAttributes::auditCreatedAtColumn($this->audit) ?? 'audit_timestamp')
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
                                ->visible(fn(): bool => $this->canTransition(true))
                                ->modalContent(fn () => $this->previewTransition(true))
                                ->action(fn () => $this->applyTransition(true, 'Reverted successfully', 'The model has been reverted successfully.')),

                            Action::make('recover')
                                ->label('Recover')
                                ->icon(Heroicon::ArrowDownOnSquare)
                                ->color('danger')
                                ->tooltip('Restore the state after these changes')
                                ->requiresConfirmation()
                                ->visible(fn(): bool => $this->canTransition(false))
                                ->modalContent(fn () => $this->previewTransition(false))
                                ->action(fn () => $this->applyTransition(false, 'Recover successfully', 'The model has been recovered successfully.')),
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
                                ->action(fn () => $this->applyRestore()),
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