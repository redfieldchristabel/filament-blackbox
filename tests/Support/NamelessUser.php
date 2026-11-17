<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A user without a `name` column: its label comes from getFilamentName(),
 * composing `first_name`/`last_name` — a value that exists only in PHP, never
 * in a single DB column, so no column-based fallback could produce it. Its
 * avatar comes from HasAvatar::getFilamentAvatarUrl(), Filament's own contract.
 */
class NamelessUser extends Authenticatable implements HasAvatar, HasName
{
    protected $table = 'nameless_users';

    protected $guarded = [];

    public function getFilamentName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }
}
