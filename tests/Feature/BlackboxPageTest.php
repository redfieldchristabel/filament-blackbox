<?php

use Blackbox\FilamentBlackbox\Pages\Blackbox;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    config()->set('blackbox.resources', [
        AuditedPost::class => ['label' => 'Post'],
        'default' => ['badge_class' => 'fi-color-gray'],
    ]);

    $this->alice = User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']);
    $this->bob = User::create(['name' => 'Bob Editor', 'email' => 'bob@example.test', 'password' => 'secret']);

    // Alice creates the post, Bob renames it: one "created" and one "updated" audit.
    $this->actingAs($this->alice);
    $this->post = AuditedPost::create(['title' => 'Alpha title']);

    $this->actingAs($this->bob);
    $this->post->update(['title' => 'Beta title']);
});

it('renders the audit page with every audit', function () {
    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('Post #' . $this->post->id)
        ->assertSee('Alice Author')
        ->assertSee('Bob Editor')
        ->assertSee('Alpha title')
        ->assertSee('Beta title');
});

it('shows the empty state when there are no audits', function () {
    Audit::query()->delete();

    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('No audit logs found');
});

it('filters audits by event', function () {
    $createdAudit = $this->post->audits()->where('event', 'created')->firstOrFail();
    $updatedAudit = $this->post->audits()->where('event', 'updated')->firstOrFail();

    $component = Livewire::test(Blackbox::class)
        ->set('data.events', ['created']);

    expect($component->instance()->getAudits()->pluck('event')->all())->toBe(['created']);

    $component
        ->assertSeeHtml('audit-row-' . $createdAudit->id)
        ->assertDontSeeHtml('audit-row-' . $updatedAudit->id);

    $component->set('data.events', ['updated']);

    expect($component->instance()->getAudits()->pluck('event')->all())->toBe(['updated']);

    $component
        ->assertSeeHtml('audit-row-' . $updatedAudit->id)
        ->assertDontSeeHtml('audit-row-' . $createdAudit->id);
});

it('filters audits by user', function () {
    $createdAudit = $this->post->audits()->where('event', 'created')->firstOrFail();
    $updatedAudit = $this->post->audits()->where('event', 'updated')->firstOrFail();

    $component = Livewire::test(Blackbox::class)
        ->set('data.users', [$this->alice->id]);

    expect($component->instance()->getAudits()->pluck('user_id')->all())->toBe([$this->alice->id]);

    $component
        ->assertSeeHtml('audit-row-' . $createdAudit->id)
        ->assertDontSeeHtml('audit-row-' . $updatedAudit->id);

    $component->set('data.users', [$this->bob->id]);

    expect($component->instance()->getAudits()->pluck('event')->all())->toBe(['updated']);

    $component
        ->assertSeeHtml('audit-row-' . $updatedAudit->id)
        ->assertDontSeeHtml('audit-row-' . $createdAudit->id);
});
