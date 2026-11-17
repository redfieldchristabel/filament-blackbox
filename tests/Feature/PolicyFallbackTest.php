<?php

use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\BlackboxActionHost;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Blackbox\FilamentBlackbox\Tests\Support\ViewOnlyPolicy;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']));

    $this->post = AuditedPost::create(['title' => 'Alpha title']);
});

it('allows a model without a policy by default', function () {
    expect(config('blackbox.authorize_without_policy'))->toBeTrue()
        ->and(blackboxCheck('view', $this->post))->toBeTrue();

    Livewire::test(BlackboxActionHost::class, ['record' => $this->post])
        ->assertActionVisible('blackbox');
});

it('denies a model without a policy when authorize_without_policy is false', function () {
    config()->set('blackbox.authorize_without_policy', false);

    expect(blackboxCheck('view', $this->post))->toBeFalse();

    Livewire::test(BlackboxActionHost::class, ['record' => $this->post])
        ->assertActionHidden('blackbox');
});

it('always delegates to the policy when there is one', function () {
    Gate::policy(AuditedPost::class, ViewOnlyPolicy::class);

    foreach ([true, false] as $withoutPolicy) {
        config()->set('blackbox.authorize_without_policy', $withoutPolicy);

        expect(blackboxCheck('view', $this->post))->toBeTrue()
            ->and(blackboxCheck('update', $this->post))->toBeFalse();
    }
});

it('denies when there is no model to check', function () {
    expect(blackboxCheck('view', null))->toBeFalse();
});
