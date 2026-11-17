<?php

use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\BlackboxActionHost;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']));

    $this->post = AuditedPost::create(['title' => 'Alpha title']);
    $this->post->update(['title' => 'Beta title']);
});

it('opens the Blackbox slide-over for an audited record', function () {
    $component = Livewire::test(BlackboxActionHost::class, ['record' => $this->post])
        ->assertActionVisible('blackbox')
        ->mountAction('blackbox')
        ->assertActionMounted('blackbox');

    $action = $component->instance()->getMountedAction();

    expect($action->isModalSlideOver())->toBeTrue();

    $content = (string) $action->getModalContent();

    expect($content)
        ->toContain('AuditedPost #' . $this->post->id)
        ->toContain('Alice Author')
        ->toContain('Alpha title')
        ->toContain('Beta title');
});
