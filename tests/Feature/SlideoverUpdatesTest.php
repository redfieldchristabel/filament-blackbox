<?php

use Blackbox\FilamentBlackbox\Pages\Blackbox;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']));

    // Five audits: one "created" and four "updated" ones (ids 1 oldest to 5 newest).
    $this->post = AuditedPost::create(['title' => 'Alpha title']);

    foreach (['Beta', 'Gamma', 'Delta', 'Epsilon'] as $title) {
        $this->post->update(['title' => " {$title} title"]);
    }
});

it('survives a Livewire update after the first render of the slide-over', function () {
    // Any Livewire update after the first render (its very first `perPage`
    // change included — the empty timeline MIS reported when opening the
    // slide-over and paging) re-runs the component's render, and without the
    // Filament contracts it threw:
    // `Schema::make(): Argument #1 ($livewire) must be of type ?HasSchemas`.
    $component = Livewire::test('blackbox::blackbox-slideover', ['record' => $this->post])
        ->set('perPage', 50);

    expect($component->instance()->audits->pluck('id')->all())->toBe([5, 4, 3, 2, 1])
        // The re-render keeps the whole timeline: one audit-item per audit.
        ->and(substr_count($component->html(), 'wire:name="blackbox::audit-item"'))->toBe(5);
});

it('renders the next page when the slide-over loads more', function () {
    // perPage 3 shows the three newest audits; "Alpha title" (the oldest
    // audit's diff) only becomes visible once loadMore() raises the page size.
    $component = Livewire::test('blackbox::blackbox-slideover', ['record' => $this->post])
        ->assertDontSee('Alpha title')
        ->call('loadMore');

    expect($component->instance()->audits->pluck('id')->all())->toBe([5, 4, 3, 2, 1])
        ->and($component->html())->toContain('Alpha title');
});

it('shows the audits when perPage is set as the first interaction on a fresh slide-over', function () {
    // MIS I-0005: the first `perPage` change IS the slide-over's first
    // Livewire update cycle, so the empty timeline is the user-visible effect
    // of that first update failing on the missing-contract
    // Schema::make TypeError, like every other update. This
    // test is kept as the symptom pin for that reported first-interaction
    // case; the fix and the mechanism are the ones tested above.
    $component = Livewire::test('blackbox::blackbox-slideover', ['record' => $this->post]);

    $component->set('perPage', 50);

    expect($component->instance()->audits->pluck('id')->all())->toBe([5, 4, 3, 2, 1])
        ->and($component->html())->toContain('Alpha title')
        ->not->toContain('No audit history found.');
});

it('survives a Livewire update of the audit-item component', function () {
    // A refresh re-runs the component's whole update cycle and re-renders its
    // content schema — the path that needs the Filament contracts in place.
    $updatedAudit = $this->post->audits()->where('event', 'updated')->orderByDesc('id')->firstOrFail();

    Livewire::test('blackbox::audit-item', ['audit' => $updatedAudit])
        ->call('$refresh')
        ->assertSee('Epsilon title')
        ->assertSee('Revert');
});

it('survives a Livewire update of the page', function () {
    $component = Livewire::test(Blackbox::class)
        ->set('perPage', 50);

    expect($component->instance()->getAudits()->getCollection()->pluck('id')->all())->toBe([5, 4, 3, 2, 1])
        // Every audit row stays scaffolded after the update cycle.
        ->and($component->html())->toContain('audit-row-1')
        ->and($component->html())->toContain('audit-row-5')
        ->and($component->html())->toContain('Alpha title');
});
