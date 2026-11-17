<?php

use Blackbox\FilamentBlackbox\Tests\Support\ConsoleDisabledPost;
use Blackbox\FilamentBlackbox\Tests\Support\ConsoleEnabledPost;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

// Pest itself runs in the console, so these saves exercise exactly what a
// queue worker, seeder or command would write.
//
// Each describe block uses a model class of its own and sets audit.console in
// beforeEach, BEFORE that class is first used (and therefore booted). A model
// class boots once per process, and what boot does depends on audit.console
// on owen-it 14.0.3 (see the comment in the second block), so neither result
// depends on which tests ran before this file.

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']));
});

describe('audit.console enabled', function () {
    beforeEach(function () {
        config(['audit.console' => true]);
    });

    it('records console audits and shows them on the timeline', function () {
        $post = ConsoleEnabledPost::create(['title' => 'Alpha title']);
        $post->update(['title' => 'Beta title']);

        // What the UI sees: two recorded rows.
        expect($post->audits()->count())->toBe(2);

        // What the UI sees: both console-written audits on the timeline.
        $component = Livewire::test('blackbox::blackbox-slideover', ['record' => $post]);

        expect(substr_count($component->html(), 'wire:name="blackbox::audit-item"'))->toBe(2)
            ->and($component->html())->toContain('Alpha title')
            ->and($component->html())->toContain('Beta title');
    });
});

describe('audit.console disabled', function () {
    beforeEach(function () {
        config(['audit.console' => false]);
    });

    it('records no console audits, so the timeline stays empty', function () {
        $post = ConsoleDisabledPost::create(['title' => 'Alpha title']);
        $post->update(['title' => 'Beta title']);

        // Upstream, read from the source of each version (not run against
        // 14.0.3 here: this suite only runs on the installed 14.0.7):
        //  - 14.0.3 (upstream v14.0.3 src/Auditable.php:80): bootAuditable()
        //    registers the observer only if static::isAuditingEnabled(), which
        //    is false in the console unless audit.console is on
        //    (src/Auditable.php:552-559), so no observer, no rows.
        //  - 14.0.7 (installed vendor, src/Auditable.php:80): bootAuditable()
        //    registers the observer on audit.enabled alone (upstream PR #1050),
        //    but dispatchAudit() re-checks isAuditingEnabled() before
        //    recording (src/AuditableObserver.php:98), so still no rows.
        // Only the rows and the rendered timeline are asserted, never owen-it
        // internals such as the instance's $auditEvent property, which differs
        // between versions.
        expect($post->audits()->count())->toBe(0);

        // What the UI sees: an empty timeline.
        Livewire::test('blackbox::blackbox-slideover', ['record' => $post])
            ->assertSee('No audit history found.');
    });
});
