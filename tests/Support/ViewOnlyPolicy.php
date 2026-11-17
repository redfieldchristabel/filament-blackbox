<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

class ViewOnlyPolicy
{
    public function view($user, AuditedPost $post): bool
    {
        return true;
    }

    public function update($user, AuditedPost $post): bool
    {
        return false;
    }
}
