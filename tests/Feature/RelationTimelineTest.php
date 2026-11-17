<?php

use Blackbox\FilamentBlackbox\Support\AuditRelations;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedArticle;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\BlackboxActionHost;
use Blackbox\FilamentBlackbox\Tests\Support\SilentLedger;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']));

    // The HTML of the slide-over timeline for a record.
    $this->timeline = function (AuditedPost $record): string {
        $component = Livewire::test(BlackboxActionHost::class, ['record' => $record])->mountAction('blackbox');

        return (string) $component->instance()->getMountedAction()->getModalContent();
    };

    $this->post = AuditedPost::create(['title' => 'Timeline post']);
    $this->post->comments()->create(['body' => 'Nested comment body']);
});

it('shows only the record audits without relations', function () {
    $html = ($this->timeline)($this->post);

    expect($html)->toContain('Timeline post')
        ->not->toContain('Nested comment body');
});

it('adds the audits of related models from config', function () {
    config()->set('blackbox.resources', [
        AuditedPost::class => ['relations' => ['comments' => '']],
        'default' => ['badge_class' => 'fi-color-gray'],
    ]);

    expect(($this->timeline)($this->post))->toContain('Timeline post')->toContain('Nested comment body');
});

it('accepts indexed relation entries in config', function () {
    config()->set('blackbox.resources', [
        AuditedPost::class => ['relations' => ['comments']],
        'default' => ['badge_class' => 'fi-color-gray'],
    ]);

    expect(($this->timeline)($this->post))->toContain('Nested comment body');
});

it('falls back to auditRelations() on the model when config has no relations', function () {
    $article = AuditedArticle::create(['title' => 'Article title']);
    $article->comments()->create(['body' => 'Article comment']);

    expect(($this->timeline)($article))->toContain('Article title')->toContain('Article comment');
});

it('prefers the config relations over auditRelations()', function () {
    config()->set('blackbox.resources', [
        AuditedArticle::class => ['relations' => []],
        'default' => ['badge_class' => 'fi-color-gray'],
    ]);

    $article = AuditedArticle::create(['title' => 'Article title']);
    $article->comments()->create(['body' => 'Article comment']);

    expect(($this->timeline)($article))->toContain('Article title')->not->toContain('Article comment');
});

it('normalises associative and indexed relation entries', function () {
    expect(AuditRelations::normalize(['profile', 'posts' => 'comments,author', 'tags' => ['a', 'b']]))
        ->toBe(['profile' => '', 'posts' => 'comments,author', 'tags' => 'a,b'])
        ->and(AuditRelations::for(new AuditedArticle))->toBe(['comments' => ''])
        ->and(AuditRelations::for(new AuditedPost))->toBe([]);
});

it('ignores an auditRelations method that is not callable from the outside', function () {
    $ledger = SilentLedger::create(['title' => 'Silent ledger']);
    $ledger->comments()->create(['body' => 'Nested comment body']);

    // A protected auditRelations() is not callable: treated as absent instead
    // of fataling through Model::__call.
    expect(AuditRelations::for($ledger))->toBe([]);

    expect(($this->timeline)($ledger))
        ->toContain('Silent ledger')
        ->not->toContain('Nested comment body');
});
