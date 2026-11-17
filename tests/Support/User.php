<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'test_users';

    protected $guarded = [];
}
