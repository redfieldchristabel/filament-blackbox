<?php

use Blackbox\FilamentBlackbox\Exceptions\ProtectedAttributeTransitionException;
use Blackbox\FilamentBlackbox\Pages\Blackbox;
use Blackbox\FilamentBlackbox\Support\AuditableAttributes;
use Blackbox\FilamentBlackbox\Tests\Support\ArrayBag;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\CountingBag;
use Blackbox\FilamentBlackbox\Tests\Support\IncludedAccount;
use Blackbox\FilamentBlackbox\Tests\Support\LegacyAudit;
use Blackbox\FilamentBlackbox\Tests\Support\MutatingAccount;
use Blackbox\FilamentBlackbox\Tests\Support\OpenLedger;
use Blackbox\FilamentBlackbox\Tests\Support\SealedDiary;
use Blackbox\FilamentBlackbox\Tests\Support\SecureAccount;
use Blackbox\FilamentBlackbox\Tests\Support\TestEnum;
use Blackbox\FilamentBlackbox\Tests\Support\VisibleArticle;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Stringable;
use Livewire\Livewire;
use OwenIt\Auditing\Exceptions\AuditableTransitionException;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->account = SecureAccount::create([
        'name' => 'Current',
        'password' => 'current-hash',
        'remember_token' => 'keep-me',
        'api_token' => 'api-live',
    ]);

    // A row written BEFORE password / remember_token / api_token were excluded.
    $this->legacy = fn (array $old, array $new) => LegacyAudit::for($this->account, $old, $new);
});

it('follows the owen-it rules of the model', function () {
    // owen-it itself keeps excluded attributes out of new audits.
    expect($this->account->audits()->firstOrFail()->new_values)->toEqual(['name' => 'Current', 'id' => $this->account->id]);

    expect(AuditableAttributes::isAuditable($this->account, 'name'))->toBeTrue()
        ->and(AuditableAttributes::isAuditable($this->account, 'password'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($this->account, 'remember_token'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($this->account, 'api_token'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($this->account, 'created_at'))->toBeFalse()
        // Not strict: nothing is hidden from the audit.
        ->and(AuditableAttributes::isAuditable(new AuditedPost, 'title'))->toBeTrue();
});

it('never shows excluded values of an old row on the page', function () {
    Audit::query()->delete();

    ($this->legacy)(
        ['name' => 'Old Name', 'password' => 'legacy-hash', 'remember_token' => 'legacy-token', 'api_token' => 'legacy-api'],
        ['name' => 'Newer Name', 'password' => 'legacy-hash-2'],
    );

    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('Old Name')
        ->assertSee('Newer Name')
        ->assertDontSee('legacy-hash')
        ->assertDontSee('legacy-token')
        ->assertDontSee('legacy-api');
});

it('offers Revert and Recover when an auditable attribute changed', function () {
    Audit::query()->delete();

    ($this->legacy)(['name' => 'Old Name', 'password' => 'legacy-hash'], ['name' => 'Newer Name']);

    Livewire::test(Blackbox::class)
        ->assertSee('Revert')
        ->assertSee('Recover');
});

it('hides Revert and Recover when only excluded fields changed', function () {
    Audit::query()->delete();

    $audit = ($this->legacy)(
        ['password' => 'legacy-hash', 'remember_token' => 'legacy-token'],
        ['password' => 'legacy-hash-2'],
    );

    expect(AuditableAttributes::canTransition($this->account, $audit, true))->toBeFalse()
        ->and(AuditableAttributes::canTransition($this->account, $audit, false))->toBeFalse();

    Livewire::test(Blackbox::class)
        ->assertDontSee('Revert')
        ->assertDontSee('Recover');
});

it('offers Revert and Recover only for updated audits', function () {
    // The account only has its own `created` audit.
    Livewire::test(Blackbox::class)
        ->assertDontSee('Revert')
        ->assertDontSee('Recover');
});

it('filters the values of an old row down to the still auditable ones', function () {
    $audit = ($this->legacy)(
        ['name' => 'Old Name', 'remember_token' => 'legacy-token', 'password' => 'legacy-hash'],
        ['name' => 'Newer Name', 'remember_token' => 'legacy-token-2'],
    );

    expect(AuditableAttributes::transitionableValues($this->account, $audit))
        ->toBe(['old' => ['name' => 'Old Name'], 'new' => ['name' => 'Newer Name']])
        ->and(array_keys(AuditableAttributes::modified($this->account, $audit)))->toBe(['name']);
});

it('reverts an old row without restoring password or remember_token', function () {
    $audit = ($this->legacy)(
        ['name' => 'Old Name', 'remember_token' => 'legacy-token', 'password' => 'legacy-hash', 'api_token' => 'legacy-api'],
        ['name' => 'Newer Name'],
    );

    AuditableAttributes::transition($this->account, $audit, true);

    expect($this->account->getDirty())->toBe(['name' => 'Old Name']);

    $this->account->save();

    $fresh = $this->account->fresh();

    expect($fresh->name)->toBe('Old Name')
        ->and($fresh->remember_token)->toBe('keep-me')
        ->and($fresh->password)->toBe('current-hash')
        ->and($fresh->api_token)->toBe('api-live');
});

it('recovers an old row without restoring password or remember_token', function () {
    $audit = ($this->legacy)(
        ['name' => 'Old Name'],
        ['name' => 'Newer Name', 'remember_token' => 'legacy-token-2', 'password' => 'legacy-hash-2'],
    );

    AuditableAttributes::transition($this->account, $audit);

    expect($this->account->getDirty())->toBe(['name' => 'Newer Name']);

    $this->account->save();

    $fresh = $this->account->fresh();

    expect($fresh->name)->toBe('Newer Name')
        ->and($fresh->remember_token)->toBe('keep-me')
        ->and($fresh->password)->toBe('current-hash');
});

it('reverts and recovers a real owen-it audit of a normal field', function () {
    $this->account->update(['name' => 'Changed']);

    $audit = $this->account->audits()->where('event', 'updated')->firstOrFail();

    $model = $this->account->fresh();
    AuditableAttributes::transition($model, $audit, true);
    expect($model->getDirty())->toBe(['name' => 'Current']);

    $model = $this->account->fresh();
    $model->name = 'Somewhere else';
    $model->syncOriginal();
    AuditableAttributes::transition($model, $audit);
    expect($model->getDirty())->toBe(['name' => 'Changed']);
});

it('still lets owen-it refuse a transition for another record', function () {
    $audit = ($this->legacy)(['name' => 'Old Name'], ['name' => 'Newer Name']);

    $other = SecureAccount::create(['name' => 'Other']);

    AuditableAttributes::transition($other, $audit, true);
})->throws(AuditableTransitionException::class);

// --- Round 3: strict visible allowlist --------------------------------------

it('rejects a strict visible allowlist key that is absent from the hydrated model', function () {
    $article = VisibleArticle::create(['title' => 'Visible title']);

    // `secret` is neither a column of the model nor in $visible, yet an old
    // row written before it was dropped still carries the value.
    $audit = LegacyAudit::for(
        $article,
        ['title' => 'Old title', 'secret' => 'legacy-secret-value'],
        ['title' => 'Visible title', 'secret' => 'legacy-secret-value-2'],
    );

    expect(AuditableAttributes::isAuditable($article, 'secret'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($article, 'title'))->toBeTrue()
        ->and(AuditableAttributes::transitionableValues($article, $audit))
        ->toBe(['old' => ['title' => 'Old title'], 'new' => ['title' => 'Visible title']])
        ->and(array_keys(AuditableAttributes::modified($article, $audit)))->toBe(['title'])
        ->and(AuditableAttributes::canTransition($article, $audit, true))->toBeTrue();

    // The secret never reaches the model, even through the transition.
    AuditableAttributes::transition($article, $audit, true);

    expect($article->getDirty())->toBe(['title' => 'Old title']);
});

it('never shows a strict allowlist key that is absent from the model on the page', function () {
    Audit::query()->delete();

    $article = VisibleArticle::create(['title' => 'Visible title']);

    LegacyAudit::for(
        $article,
        ['title' => 'Old title', 'secret' => 'legacy-secret-value'],
        ['title' => 'Visible title', 'secret' => 'legacy-secret-value-2'],
    );

    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('Old title')
        ->assertDontSee('legacy-secret-value');
});

// --- Round 3: values are filtered before getModified() formats them ---------

it('never formats an excluded legacy value through its current encrypted cast', function () {
    $diary = SealedDiary::create(['title' => 'First entry', 'secret_note' => 'really encrypted now']);

    $legacy = LegacyAudit::for(
        $diary,
        ['title' => 'Old entry', 'secret_note' => 'plain-legacy-not-encrypted'],
        ['title' => 'First entry'],
    );

    // owen-it's own getModified() formats every stored value through the
    // auditable's casts: the plain legacy value is not decryptable.
    expect(fn () => $legacy->getModified())->toThrow(DecryptException::class);

    // Blackbox filters first, so the timeline still renders.
    expect(array_keys(AuditableAttributes::modified($diary, $legacy)))->toBe(['title']);

    AuditableAttributes::transition($diary, $legacy, true);

    expect($diary->getDirty())->toBe(['title' => 'Old entry'])
        ->and($diary->getAttribute('secret_note'))->toBe('really encrypted now');
});

// --- Round 3: non scalar values (owen-it mirror) -----------------------------

it('mirrors the owen-it array exclusion, including audit.allowed_array_values', function () {
    $bag = new ArrayBag;
    $bag->setRawAttributes(['name' => 'plain', 'meta' => ['nested' => 'value'], 'absent' => null]);

    expect(AuditableAttributes::isAuditable($bag, 'name'))->toBeTrue()
        ->and(AuditableAttributes::isAuditable($bag, 'meta'))->toBeFalse()
        ->and(in_array('meta', AuditableAttributes::excluded($bag), true))->toBeTrue();

    config()->set('audit.allowed_array_values', true);

    expect(AuditableAttributes::isAuditable($bag, 'meta'))->toBeTrue()
        ->and(in_array('meta', AuditableAttributes::excluded($bag), true))->toBeFalse();
});

it('mirrors the owen-it object exclusions', function () {
    $bag = new ArrayBag;
    $bag->setRawAttributes([
        'plain' => 'x',
        'object' => new stdClass,
        'stringable' => new Stringable('hi'),
        'suit' => TestEnum::Spades,
    ]);

    expect(AuditableAttributes::isAuditable($bag, 'object'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($bag, 'stringable'))->toBeTrue()
        ->and(AuditableAttributes::isAuditable($bag, 'suit'))->toBeTrue();
});

// --- Round 3: a setter on an allowed field dirtying an excluded one ----------

it('refuses to transition when a setter dirties an excluded attribute', function () {
    $account = MutatingAccount::create(['name' => 'Current', 'password' => 'hash', 'remember_token' => 'keep-me']);

    $audit = LegacyAudit::for($account, ['name' => 'Old Name'], ['name' => 'Newer Name']);

    $model = $account->fresh();

    try {
        AuditableAttributes::transition($model, $audit, true);
        $this->fail('The transition should have been refused.');
    } catch (ProtectedAttributeTransitionException $exception) {
        expect($exception->getMessage())->toContain('api_token');
    }

    // The model was reset: nothing can be saved afterwards.
    expect($model->getDirty())->toBe([])
        ->and($model->getAttribute('name'))->toBe('Current');

    $model->save();

    expect($model->fresh()->getAttribute('name'))->toBe('Current')
        ->and($model->fresh()->getAttribute('api_token'))->toBe('token-for-Current');
});

it('never renders an excluded dirty key in the preview and refuses the save', function () {
    $account = MutatingAccount::create(['name' => 'Current', 'password' => 'hash', 'remember_token' => 'keep-me']);

    $audit = LegacyAudit::for($account, ['name' => 'Old Name'], ['name' => 'Newer Name']);

    $component = Livewire::test('blackbox::audit-item', ['audit' => $audit]);
    $instance = $component->instance();

    // The preview reports a generic refusal only: neither the protected
    // attribute name, its values, nor the detailed exception message appear.
    $preview = (string) (fn () => $this->previewTransition(true))->call($instance);

    expect($preview)->toContain('modify protected fields')
        ->not->toContain('api_token')
        ->not->toContain('token-for-Old Name')
        ->not->toContain('token-for-Current')
        ->not->toContain('Old Name');

    // The action refuses too: the record is untouched, and the notification's
    // exact body is the generic refusal (no protected attribute name or value
    // in it), asserted through Filament's public testing API.
    (fn () => $this->applyTransition(true, 'Reverted', 'done'))->call($instance);

    Notification::assertNotified(
        Notification::make()
            ->title('Could not apply this audit')
            ->body("This change can't be reverted because it would modify protected fields.")
            ->danger(),
    );

    expect($account->fresh()->getAttribute('name'))->toBe('Current')
        ->and($account->fresh()->getAttribute('api_token'))->toBe('token-for-Current');
});

// --- Round 5: non-strict models FOLLOW owen-it -------------------------------

it('protects a non-strict model exactly like owen-it: auditExclude only, hidden does not count', function () {
    $ledger = OpenLedger::create([
        'name' => 'Current',
        'password' => 'current-hash',
        'remember_token' => 'keep-me',
        'api_token' => 'api-live',
    ]);

    Audit::query()->delete();

    // A row written BEFORE password / remember_token were excluded still
    // carries them (and api_token, which this non-strict model audits).
    $legacy = LegacyAudit::for(
        $ledger,
        ['name' => 'Old Name', 'password' => 'legacy-hash', 'remember_token' => 'legacy-token', 'api_token' => 'legacy-api'],
        ['name' => 'Newer Name', 'password' => 'legacy-hash-2'],
    );

    // owen-it's rule for non-strict models: $hidden does NOT exclude. The
    // secrets are protected because they sit in $auditExclude, nothing else.
    expect(AuditableAttributes::isAuditable($ledger, 'password'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($ledger, 'remember_token'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($ledger, 'api_token'))->toBeTrue()
        ->and(AuditableAttributes::isAuditable($ledger, 'name'))->toBeTrue();

    // The page never shows the excluded legacy values.
    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('Old Name')
        ->assertDontSee('legacy-hash')
        ->assertDontSee('legacy-hash-2')
        ->assertDontSee('legacy-token');

    // Revert restores only auditable values: password / remember_token stay.
    AuditableAttributes::transition($ledger, $legacy, true);

    expect($ledger->getDirty())->toBe(['name' => 'Old Name', 'api_token' => 'legacy-api']);

    $ledger->save();

    $fresh = $ledger->fresh();

    expect($fresh->getAttribute('name'))->toBe('Old Name')
        ->and($fresh->getAttribute('password'))->toBe('current-hash')
        ->and($fresh->getAttribute('remember_token'))->toBe('keep-me');
});

// --- Round 5: $auditInclude allowlist ----------------------------------------

it('hides and never restores keys outside the auditInclude list', function () {
    $account = IncludedAccount::create(['name' => 'Current', 'password' => 'hash', 'remember_token' => 'keep-me', 'api_token' => 'api-live']);

    // owen-it itself audits only what the include list allows — `id` too is
    // outside it.
    expect($account->audits()->firstOrFail()->new_values)->toEqual(['name' => 'Current']);

    Audit::query()->delete();

    $legacy = LegacyAudit::for(
        $account,
        ['name' => 'Old Name', 'password' => 'legacy-hash', 'api_token' => 'legacy-api', 'remember_token' => 'legacy-token'],
        ['name' => 'Newer Name', 'password' => 'legacy-hash-2'],
    );

    expect(AuditableAttributes::isAuditable($account, 'name'))->toBeTrue()
        ->and(AuditableAttributes::isAuditable($account, 'password'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($account, 'api_token'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($account, 'remember_token'))->toBeFalse()
        ->and(AuditableAttributes::transitionableValues($account, $legacy))
        ->toBe(['old' => ['name' => 'Old Name'], 'new' => ['name' => 'Newer Name']]);

    // The page shows only what the include list allows.
    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('Old Name')
        ->assertDontSee('legacy-hash')
        ->assertDontSee('legacy-api')
        ->assertDontSee('legacy-token');

    // Revert restores only `name`.
    AuditableAttributes::transition($account, $legacy, true);

    expect($account->getDirty())->toBe(['name' => 'Old Name']);

    $account->save();

    $fresh = $account->fresh();

    expect($fresh->getAttribute('name'))->toBe('Old Name')
        ->and($fresh->getAttribute('password'))->toBe('hash')
        ->and($fresh->getAttribute('api_token'))->toBe('api-live')
        ->and($fresh->getAttribute('remember_token'))->toBe('keep-me');
});

// --- Round 6: exclusions are computed fresh, never memoised ------------------

it('recomputes exclusions on every lookup so rule changes are always reflected', function () {
    $bag = new ArrayBag;
    $bag->setRawAttributes(['name' => 'plain', 'meta' => ['nested' => 'value']]);

    // Answered consistently, however often the timeline asks.
    expect(in_array('meta', AuditableAttributes::excluded($bag), true))->toBeTrue()
        ->and(in_array('meta', AuditableAttributes::excluded($bag), true))->toBeTrue();

    // The model's current attributes are part of the rules (the non scalar
    // check), so a change is reflected on the next lookup — the same
    // invalidation a transitionTo() causes. A config change is covered by the
    // `audit.allowed_array_values` test above, which flips it on one instance.
    $bag->setRawAttributes(['name' => 'plain', 'meta' => 'scalar now']);

    expect(in_array('meta', AuditableAttributes::excluded($bag), true))->toBeFalse();
});

it('never serialises the model to fingerprint exclusions, so closure attributes do not crash', function () {
    // The removed memo fingerprinted the model's attributes with serialize():
    // any non serialisable attribute value (a Closure) threw on EVERY lookup.
    $bag = new ArrayBag;
    $bag->setRawAttributes(['name' => 'plain', 'callback' => fn (): string => 'value']);

    expect(in_array('callback', AuditableAttributes::excluded($bag), true))->toBeTrue()
        ->and(AuditableAttributes::isAuditable($bag, 'callback'))->toBeFalse()
        ->and(AuditableAttributes::isAuditable($bag, 'name'))->toBeTrue();
});

it('answers a filter() of any size with a single rule computation', function () {
    // filter() computes the exclusion rules ONCE and reuses them for every
    // key: `getCreatedAtColumn()` is only read while the rules are computed
    // (the timestamp opt-out) — its call count proves how often that happened.
    $bag = new CountingBag;
    $bag->setRawAttributes(['name' => 'plain', 'meta' => ['nested' => 'value']]);

    CountingBag::$ruleComputations = 0;

    AuditableAttributes::filter($bag, ['meta' => 'b']);
    $afterOneKey = CountingBag::$ruleComputations;

    // Guard the relative comparison below: it would pass vacuously (0 * 2 = 0)
    // if the probe were never hit.
    expect($afterOneKey)->toBeGreaterThan(0);

    $filtered = AuditableAttributes::filter($bag, ['name' => 'a', 'meta' => 'b', 'other' => 'c', 'one more' => 'd']);

    expect(array_keys($filtered))->toBe(['name', 'other', 'one more'])
        // Relative, not pinned to the per-computation probe count: the
        // four-key filter cost exactly what the one-key filter cost, so the
        // rules are computed once per CALL, independent of its size.
        ->and(CountingBag::$ruleComputations)->toBe($afterOneKey * 2);
});
