<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

class NoRestorePolicy
{
    public function view($user, TrashedArticle $article): bool
    {
        return true;
    }

    public function restore($user, TrashedArticle $article): bool
    {
        return false;
    }
}
