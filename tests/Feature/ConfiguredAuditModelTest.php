<?php

use Blackbox\FilamentBlackbox\FilamentBlackbox;
use Blackbox\FilamentBlackbox\Pages\Blackbox;
use Blackbox\FilamentBlackbox\Support\AuditableAttributes;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\BlackboxActionHost;
use Blackbox\FilamentBlackbox\Tests\Support\LegacyAudit;
use Blackbox\FilamentBlackbox\Tests\Support\ScheduledAudit;
use Blackbox\FilamentBlackbox\Tests\Support\SecureAccount;
use Blackbox\FilamentBlackbox\Tests\Support\TaggedAudit;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\View\ViewException;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // A configured audit implementation that implements owen-it's contract
    // WITHOUT extending owen-it's default Audit model.
    config()->set('audit.implementation', TaggedAudit::class);

    $this->actingAs(User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']));

    $this->post = AuditedPost::create(['title' => 'Original title']);
    $this->post->update(['title' => 'Renamed title']);
});

it('renders the page for a contract-only audit model', function () {
    $updated = $this->post->audits()->where('event', 'updated')->firstOrFail();

    expect($updated)->toBeInstanceOf(TaggedAudit::class);

    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('Alice Author')
        ->assertSee('Original title')
        ->assertSee('Renamed title');
});

it('renders the slide-over timeline for a contract-only audit model', function () {
    $component = Livewire::test(BlackboxActionHost::class, ['record' => $this->post])
        ->mountAction('blackbox');

    $content = (string) $component->instance()->getMountedAction()->getModalContent();

    expect($content)
        ->toContain('AuditedPost #' . $this->post->id)
        ->toContain('Alice Author')
        ->toContain('Original title')
        ->toContain('Renamed title');
});

it('transitions through a contract-only audit model', function () {
    $updated = $this->post->audits()->where('event', 'updated')->firstOrFail();

    $model = $this->post->fresh();

    AuditableAttributes::transition($model, $updated, true);

    expect($model->getDirty())->toBe(['title' => 'Original title'])
        ->and(array_keys(AuditableAttributes::modified($model, $updated)))->toBe(['title']);
});

it('rejects an audit that does not implement the Audit contract', function () {
    // An auditable is not an audit: mounting it must be refused. Livewire
    // wraps the mount exception of the initial render in a ViewException.
    Livewire::test('blackbox::audit-item', ['audit' => new AuditedPost]);
})->throws(ViewException::class, 'The audit must be an Eloquent model implementing');

// --- Round 4 M4b: custom timestamp column names ------------------------------

it("carries a custom audit model's timestamp columns into the filtered copy", function () {
    // An audit implementation whose CREATED_AT/UPDATED_AT are
    // performed_at/performed_until, not created_at/updated_at.
    config()->set('audit.implementation', ScheduledAudit::class);

    $post = AuditedPost::create(['title' => 'Original title']);
    $post->update(['title' => 'Renamed title']);

    $audit = $post->audits()->where('event', 'updated')->firstOrFail();

    expect($audit)->toBeInstanceOf(ScheduledAudit::class)
        ->and($audit->getAttribute('performed_at'))->not->toBeNull()
        ->and($audit->getAttribute('performed_until'))->not->toBeNull();

    // replicate() drops performed_at/performed_until; the copy must carry them
    // again under the model's real column names, so resolveData() can
    // serialise them and transitionTo() sees the same metadata as the row.
    $copy = AuditableAttributes::filteredCopy($post->fresh(), $audit);

    expect($copy->getAttribute('performed_at'))->toEqual($audit->getAttribute('performed_at'))
        ->and($copy->getAttribute('performed_until'))->toEqual($audit->getAttribute('performed_until'));

    // Display and transition work end-to-end with the custom timestamps.
    expect(array_keys(AuditableAttributes::modified($post->fresh(), $audit)))->toBe(['title']);

    $model = $post->fresh();
    AuditableAttributes::transition($model, $audit, true);

    expect($model->getDirty())->toBe(['title' => 'Original title']);
});

// --- Round 5: renamed audit timestamp columns everywhere ---------------------

it('renders the page and slide-over when the audit model renames its timestamp columns', function () {
    config()->set('audit.implementation', ScheduledAudit::class);

    // A clean table: rows of ANOTHER implementation (default created_at
    // columns) hydrated as ScheduledAudit have a NULL performed_at, which
    // owen-it's own resolveData() cannot serialise at all.
    Audit::query()->delete();

    $post = AuditedPost::create(['title' => 'Original title']);
    $post->update(['title' => 'Renamed title']);

    // created_at is NULL on these rows: the views must read the model's real
    // created-at column (performed_at) instead of the hardcoded created_at.
    $audit = $post->audits()->firstOrFail();

    expect($audit)->toBeInstanceOf(ScheduledAudit::class)
        ->and($audit->getAttribute('created_at'))->toBeNull();

    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('Original title')
        ->assertSee('Renamed title');

    $component = Livewire::test(BlackboxActionHost::class, ['record' => $post])->mountAction('blackbox');

    $content = (string) $component->instance()->getMountedAction()->getModalContent();

    expect($content)
        ->toContain('Original title')
        ->toContain('Renamed title');
});

it('applies both date filters to the audit model\'s own created-at column', function () {
    config()->set('audit.implementation', ScheduledAudit::class);

    Audit::query()->delete();

    $post = AuditedPost::create(['title' => 'Scheduled post']);

    // Performed three days ago; created_at stays NULL on every row of the
    // scheduled audit model, so a created_at filter would find nothing.
    $performed = now()->subDays(3);
    ScheduledAudit::query()->toBase()->update(['performed_at' => $performed->copy(), 'performed_until' => $performed->copy()]);

    $ids = fn (array $filters): array => app(FilamentBlackbox::class)->getAudits($filters)->pluck('id')->all();

    expect($ids([
        'created_from' => $performed->copy()->subDay()->toDateString(),
        'created_until' => $performed->copy()->addDay()->toDateString(),
    ]))->not->toBeEmpty()
        ->and($ids(['created_from' => now()->toDateString()]))->toBe([])
        ->and($ids(['created_until' => now()->subDays(4)->toDateString()]))->toBe([]);
});

// --- Round 5: the slide-over never shows excluded legacy values --------------

it('does not show excluded values of a legacy row in the slide-over', function () {
    $account = SecureAccount::create([
        'name' => 'Current',
        'password' => 'current-hash',
        'remember_token' => 'keep-me',
        'api_token' => 'api-live',
    ]);

    Audit::query()->delete();

    // A row written BEFORE password / remember_token / api_token were excluded.
    LegacyAudit::for(
        $account,
        ['name' => 'Old Name', 'password' => 'legacy-hash', 'remember_token' => 'legacy-token', 'api_token' => 'legacy-api'],
        ['name' => 'Newer Name', 'password' => 'legacy-hash-2'],
    );

    $component = Livewire::test(BlackboxActionHost::class, ['record' => $account])->mountAction('blackbox');

    $content = (string) $component->instance()->getMountedAction()->getModalContent();

    expect($content)
        ->toContain('Old Name')
        ->toContain('Newer Name')
        ->not->toContain('legacy-hash')
        ->not->toContain('legacy-token')
        ->not->toContain('legacy-api');
});

// --- Round 5: a generic refusal when owen-it itself refuses the transition ---

it('shows a generic refusal when owen-it refuses the transition and logs the real message', function () {
    Audit::query()->delete();

    // A legacy row carrying a key that is no longer an attribute of the
    // (non-strict) model: the filter keeps it auditable, owen-it's
    // transitionTo() refuses the incompatibility.
    $audit = LegacyAudit::for(
        $this->post,
        ['title' => 'Old title', 'ghost_key' => 'ghost-value'],
        ['title' => 'Newer title'],
    );

    Log::spy();

    $component = Livewire::test('blackbox::audit-item', ['audit' => $audit]);
    $instance = $component->instance();

    // The preview shows the generic refusal only (Blade escapes the
    // apostrophe): neither owen-it's message nor the incompatible key or its
    // value appear.
    $preview = (string) (fn () => $this->previewTransition(true))->call($instance);

    expect($preview)
        ->toContain('applied to the current record')
        ->not->toContain('Incompatibility')
        ->not->toContain('ghost_key')
        ->not->toContain('ghost-value')
        ->not->toContain('Old title');

    // The real message and the incompatible keys go to the log.
    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => str_contains($message, 'Incompatibility')
            && $context['incompatibilities'] === ['ghost_key'],
    );

    // The notification on apply discloses nothing either — its exact body is
    // the generic refusal (no owen-it message, no key or value names),
    // asserted through Filament's public testing API — and nothing is saved.
    (fn () => $this->applyTransition(true, 'Reverted', 'done'))->call($instance);

    Notification::assertNotified(
        Notification::make()
            ->title('Could not apply this audit')
            ->body("This audit can't be applied to the current record.")
            ->danger(),
    );

    expect($this->post->fresh()->title)->toBe('Renamed title');
});
