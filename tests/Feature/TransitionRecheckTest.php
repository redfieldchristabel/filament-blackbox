<?php

use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\LegacyAudit;
use Blackbox\FilamentBlackbox\Tests\Support\NoRestorePolicy;
use Blackbox\FilamentBlackbox\Tests\Support\SecureAccount;
use Blackbox\FilamentBlackbox\Tests\Support\TrashedArticle;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Blackbox\FilamentBlackbox\Tests\Support\ViewOnlyPolicy;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']));

    $this->post = AuditedPost::create(['title' => 'Alpha title']);
    $this->post->update(['title' => 'Beta title']);

    $this->updatedAudit = $this->post->audits()->where('event', 'updated')->firstOrFail();
});

// --- The real action path (what a browser actually sends) -------------------

it('applies an authorised Revert through the real action path', function () {
    // Positive control for the refusals below: the same action, mounted and
    // called the way Filament's own JS does, IS allowed for this user.
    Livewire::test('blackbox::audit-item', ['audit' => $this->updatedAudit])
        ->callAction(TestAction::make('rollback')->schemaComponent(true, 'content'));

    expect($this->post->fresh()->title)->toBe('Alpha title');

    Notification::assertNotified('Reverted successfully');
});

it('refuses an unauthorised Revert through the real action path', function () {
    // The policy denies `update` while still allowing `view`, so the
    // component mounts and renders — only the action must refuse.
    Gate::policy(AuditedPost::class, ViewOnlyPolicy::class);

    // What a tampered client would send: mounting and calling the action
    // directly, without rendering it first. Filament refuses to resolve a
    // hidden schema action, and the server-side re-check in
    // applyTransition() refuses anything that slips through that.
    Livewire::test('blackbox::audit-item', ['audit' => $this->updatedAudit])
        ->mountAction(TestAction::make('rollback')->schemaComponent(true, 'content'))
        ->callMountedAction();

    expect($this->post->fresh()->title)->toBe('Beta title');

    Notification::assertNotNotified('Reverted successfully');
});

it('applies an authorised Recover through the real action path', function () {
    // Move the record away silently (no new audit): Recover must bring the
    // recorded `updated` state back.
    $this->post->title = 'Gamma title';
    $this->post->saveQuietly();

    Livewire::test('blackbox::audit-item', ['audit' => $this->updatedAudit])
        ->callAction(TestAction::make('recover')->schemaComponent(true, 'content'));

    expect($this->post->fresh()->title)->toBe('Beta title');

    Notification::assertNotified('Recover successfully');
});

it('refuses an unauthorised Recover through the real action path', function () {
    Gate::policy(AuditedPost::class, ViewOnlyPolicy::class);

    $this->post->title = 'Gamma title';
    $this->post->saveQuietly();

    Livewire::test('blackbox::audit-item', ['audit' => $this->updatedAudit])
        ->mountAction(TestAction::make('recover')->schemaComponent(true, 'content'))
        ->callMountedAction();

    expect($this->post->fresh()->title)->toBe('Gamma title');

    Notification::assertNotNotified('Recover successfully');
});

it('applies an authorised Restore through the real action path', function () {
    $article = TrashedArticle::create(['title' => 'First title']);
    $article->delete();

    $deletedAudit = $article->audits()->where('event', 'deleted')->orderByDesc('id')->firstOrFail();

    Livewire::test('blackbox::audit-item', ['audit' => $deletedAudit])
        ->callAction(TestAction::make('restore')->schemaComponent(true, 'content'));

    expect($article->fresh()->trashed())->toBeFalse()
        ->and($article->fresh()->title)->toBe('First title');

    Notification::assertNotified('Restored successfully');
});

it('refuses a Restore for a user without the restore ability through the real action path', function () {
    // The policy denies `restore` while still allowing `view`, so the
    // component mounts and renders the deleted audit: only the restore must
    // be refused — the action's visible() is not trusted server-side.
    Gate::policy(TrashedArticle::class, NoRestorePolicy::class);

    $article = TrashedArticle::create(['title' => 'First title']);
    $article->delete();

    $deletedAudit = $article->audits()->where('event', 'deleted')->orderByDesc('id')->firstOrFail();

    Livewire::test('blackbox::audit-item', ['audit' => $deletedAudit])
        ->mountAction(TestAction::make('restore')->schemaComponent(true, 'content'))
        ->callMountedAction();

    expect($article->fresh()->trashed())->toBeTrue();

    Notification::assertNotNotified('Restored successfully');
});

// --- The server-side re-check itself (defense in depth behind the path above)
// These call the protected appliers directly, the way a crafted request would
// reach them if the action resolution above ever changed.

it('refuses a Revert called directly by a user who may not update the record', function () {
    Gate::policy(AuditedPost::class, ViewOnlyPolicy::class);

    $component = Livewire::test('blackbox::audit-item', ['audit' => $this->updatedAudit]);
    $instance = $component->instance();

    (fn () => $this->applyTransition(true, 'Reverted successfully', 'done'))->call($instance);

    expect($this->post->fresh()->title)->toBe('Beta title');

    // The refusal is reported (generic detail only); the success notification
    // never fires.
    Notification::assertNotified('Action not available');
    Notification::assertNotNotified('Reverted successfully');
});

it('refuses a Revert preview called directly by a user who may not update the record', function () {
    Gate::policy(AuditedPost::class, ViewOnlyPolicy::class);

    $component = Livewire::test('blackbox::audit-item', ['audit' => $this->updatedAudit]);
    $instance = $component->instance();

    $preview = (fn () => $this->previewTransition(true))->call($instance);

    expect($preview)->toBeNull();
});

it('refuses a transition of an audit that is not an updated one', function () {
    // A `created` audit carries the state the record was born with: Reverting
    // or Recovering it would change nothing, so the server refuses it.
    $createdAudit = $this->post->audits()->where('event', 'created')->firstOrFail();

    $component = Livewire::test('blackbox::audit-item', ['audit' => $createdAudit]);
    $instance = $component->instance();

    (fn () => $this->applyTransition(true, 'Reverted successfully', 'done'))->call($instance);

    expect($this->post->fresh()->title)->toBe('Beta title');

    Notification::assertNotified('Action not available');
});

it('refuses a transition whose auditable attributes are all excluded now', function () {
    $account = SecureAccount::create(['name' => 'Current', 'password' => 'current-hash', 'remember_token' => 'keep-me', 'api_token' => 'api-live']);

    Audit::query()->delete();

    // A row written before password / remember_token were excluded: nothing
    // auditable remains, so neither state offers a transition.
    $audit = LegacyAudit::for(
        $account,
        ['password' => 'legacy-hash', 'remember_token' => 'legacy-token'],
        ['password' => 'legacy-hash-2'],
    );

    $component = Livewire::test('blackbox::audit-item', ['audit' => $audit]);
    $instance = $component->instance();

    (fn () => $this->applyTransition(true, 'Reverted successfully', 'done'))->call($instance);

    expect($account->fresh()->getAttribute('name'))->toBe('Current');

    Notification::assertNotified('Action not available');
});

it('refuses a Recover onto a soft-deleted record', function () {
    // Recovering re-applies the recorded new state to the live record: when
    // the record itself is soft-deleted, the Restore action owns that move.
    $article = TrashedArticle::create(['title' => 'First title']);
    $article->update(['title' => 'Second title']);
    $article->delete();

    $trashed = TrashedArticle::withTrashed()->findOrFail($article->id);

    $audit = $trashed->audits()->where('event', 'updated')->orderByDesc('id')->firstOrFail();

    $component = Livewire::test('blackbox::audit-item', ['audit' => $audit]);
    $instance = $component->instance();

    (fn () => $this->applyTransition(false, 'Recovered successfully', 'done'))->call($instance);

    expect($trashed->fresh()->title)->toBe('Second title')
        ->and($trashed->fresh()->deleted_at)->not->toBeNull();

    Notification::assertNotified('Action not available');
});

it('refuses a Restore called directly by a user who may not restore the record', function () {
    Gate::policy(TrashedArticle::class, NoRestorePolicy::class);

    $article = TrashedArticle::create(['title' => 'First title']);
    $article->delete();

    $deletedAudit = $article->audits()->where('event', 'deleted')->orderByDesc('id')->firstOrFail();

    $component = Livewire::test('blackbox::audit-item', ['audit' => $deletedAudit]);
    $instance = $component->instance();

    (fn () => $this->applyRestore())->call($instance);

    expect($article->fresh()->trashed())->toBeTrue();

    Notification::assertNotified('Action not available');
    Notification::assertNotNotified('Restored successfully');
});

it('refuses a Restore of a record that is no longer soft-deleted', function () {
    // The record already came back (restored by someone else): the audit is
    // still its `deleted` one, but there is nothing left to restore.
    $article = TrashedArticle::create(['title' => 'First title']);
    $article->delete();

    $deletedAudit = $article->audits()->where('event', 'deleted')->orderByDesc('id')->firstOrFail();

    $article->restore();

    $component = Livewire::test('blackbox::audit-item', ['audit' => $deletedAudit]);
    $instance = $component->instance();

    (fn () => $this->applyRestore())->call($instance);

    expect($article->fresh()->trashed())->toBeFalse()
        ->and($article->fresh()->title)->toBe('First title');

    Notification::assertNotified('Action not available');
});
