<?php

use Blackbox\FilamentBlackbox\FilamentBlackbox;
use Blackbox\FilamentBlackbox\Pages\Blackbox;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\LegacyAudit;
use Filament\Facades\Filament;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Audit::query()->delete();

    $this->post = AuditedPost::create(['title' => 'Alpha title']);
    $this->audit = $this->post->audits()->firstOrFail();
});

it('offers the restored event in the filter', function () {
    expect(Blackbox::getEventOptions())->toHaveKey('restored');

    $restored = LegacyAudit::for($this->post, [], ['title' => 'Alpha title'], 'restored');

    $component = Livewire::test(Blackbox::class)->set('data.events', ['restored']);

    expect($component->instance()->getAudits()->pluck('id')->all())->toBe([$restored->id]);
});

it('includes the whole end day in created_until', function () {
    Audit::query()->whereKey($this->audit->id)->update(['created_at' => '2026-03-10 18:30:00']);

    $ids = fn (array $filters): array => app(FilamentBlackbox::class)->getAudits($filters)->pluck('id')->all();

    expect($ids(['created_until' => '2026-03-10']))->toBe([$this->audit->id])
        ->and($ids(['created_until' => '2026-03-09']))->toBe([])
        ->and($ids(['created_from' => '2026-03-10']))->toBe([$this->audit->id])
        ->and($ids(['created_from' => '2026-03-11']))->toBe([])
        ->and($ids(['created_from' => '2026-03-10', 'created_until' => '2026-03-10']))->toBe([$this->audit->id]);
});

it('includes a fractional-second audit at the end of the created_until day', function () {
    Audit::query()->delete();

    $post = AuditedPost::create(['title' => 'Late post']);
    $audit = $post->audits()->firstOrFail();

    // 23:59:59.500 belongs to 2026-03-10; an `<= endOfDay()` binding would drop it.
    Audit::query()->whereKey($audit->id)->toBase()->update(['created_at' => '2026-03-10 23:59:59.500']);

    $ids = fn (array $filters): array => app(FilamentBlackbox::class)->getAudits($filters)->pluck('id')->all();

    expect($ids(['created_until' => '2026-03-10']))->toBe([$audit->id])
        ->and($ids(['created_until' => '2026-03-09']))->toBe([]);
});

// --- Round 6: day-granular filters, defensive date parsing --------------------

it('keeps the date filters day-granular when a filter value carries a time', function () {
    Audit::query()->whereKey($this->audit->id)->toBase()->update(['created_at' => '2026-03-10 00:30:00']);

    $ids = fn (array $filters): array => app(FilamentBlackbox::class)->getAudits($filters)->pluck('id')->all();

    // The filters cover whole days (documented): a time component in a filter
    // value does not narrow the day — the date pickers send date-only strings.
    expect($ids(['created_from' => '2026-03-10 15:00:00']))->toBe([$this->audit->id])
        ->and($ids(['created_until' => '2026-03-10 09:00:00']))->toBe([$this->audit->id])
        ->and($ids(['created_from' => '2026-03-11 08:00:00']))->toBe([]);
});

it('ignores unparsable date filter values instead of throwing', function () {
    $ids = fn (array $filters): array => app(FilamentBlackbox::class)->getAudits($filters)->pluck('id')->all();

    // A tampered Livewire value must not 500 the page: the filter does not
    // apply, every audit stays visible.
    expect($ids(['created_from' => 'not-a-date']))->toBe([$this->audit->id])
        ->and($ids(['created_until' => 'not-a-date']))->toBe([$this->audit->id])
        // Only the unparsable bound is dropped; the parsable one still applies.
        ->and($ids(['created_from' => 'not-a-date', 'created_until' => '2026-03-09']))->toBe([]);
});
