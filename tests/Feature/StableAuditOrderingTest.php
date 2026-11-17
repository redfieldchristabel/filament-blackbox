<?php

use Blackbox\FilamentBlackbox\FilamentBlackbox;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Filament\Facades\Filament;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']));

    // Seven audits written within the same second: one "created" and six
    // "updated" ones. Their auto-increment ids are 1 (oldest) to 7 (newest).
    $this->post = AuditedPost::create(['title' => 'Alpha title']);

    foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $letter) {
        $this->post->update(['title' => "Title {$letter}"]);
    }

    Audit::query()->update(['created_at' => '2026-01-01 12:00:00']);
});

afterEach(function () {
    // The first test replaces CursorPaginator's GLOBAL (static) cursor
    // resolver; restore the framework default that PaginationState registers
    // so the override cannot leak into other cursor-paginated tests.
    CursorPaginator::currentCursorResolver(function ($cursorName = 'cursor') {
        return Cursor::fromEncoded(request()->input($cursorName));
    });
});

it('pages newest-first without duplicates or gaps when audits share a created-at second', function () {
    // All seven audits share one created-at value: the cursor must fall back
    // to the audit key as the tiebreaker, or same-second rows are skipped.
    $cursor = null;
    CursorPaginator::currentCursorResolver(function () use (&$cursor) {
        return $cursor;
    });

    $pages = [];

    do {
        $page = app(FilamentBlackbox::class)->getAudits(perPage: 3);

        $pages[] = $page->getCollection()->pluck('id')->all();

        $cursor = $page->nextCursor();
    } while ($cursor !== null);

    expect($pages)->toBe([[7, 6, 5], [4, 3, 2], [1]]);
});

it('orders the slide-over timeline newest-first when audits share a created-at second', function () {
    $component = Livewire::test('blackbox::blackbox-slideover', ['record' => $this->post]);

    expect($component->instance()->audits->pluck('id')->all())->toBe([7, 6, 5]);

    $component->call('loadMore');

    expect($component->instance()->audits->pluck('id')->all())->toBe([7, 6, 5, 4, 3, 2]);

    $component->call('loadMore');

    expect($component->instance()->audits->pluck('id')->all())->toBe([7, 6, 5, 4, 3, 2, 1]);
});

it('orders by created_at first and uses the key only as the tie-break', function () {
    // Ids 1 (oldest write) to 7 (newest write), all at 12:00:00 from the
    // setup. Then break the id order on purpose:
    //  - id 3 is the NEWEST row by time although it has a LOW id,
    //  - id 6 is the OLDEST row by time although it has a HIGH id,
    //  - ids 7, 5, 4, 2 and 1 still tie on the same second.
    Audit::query()->whereKey(3)->update(['created_at' => '2026-01-01 12:00:05']);
    Audit::query()->whereKey(6)->update(['created_at' => '2026-01-01 11:59:55']);

    // created_at DESC is the primary order; the key (DESC) only separates the
    // ties. A key-only order would give [7, 6, 5, ...] instead.
    $expected = [3, 7, 5, 4, 2, 1, 6];

    // Page: cursor-paginated, three rows at a time.
    $cursor = null;
    CursorPaginator::currentCursorResolver(function () use (&$cursor) {
        return $cursor;
    });

    $pages = [];

    do {
        $page = app(FilamentBlackbox::class)->getAudits(perPage: 3);

        $pages[] = $page->getCollection()->pluck('id')->all();

        $cursor = $page->nextCursor();
    } while ($cursor !== null);

    expect($pages)->toBe([[3, 7, 5], [4, 2, 1], [6]]);

    // Slide-over: the whole timeline in one go.
    $component = Livewire::test('blackbox::blackbox-slideover', ['record' => $this->post])
        ->set('perPage', 50);

    expect($component->instance()->audits->pluck('id')->all())->toBe($expected);
});
