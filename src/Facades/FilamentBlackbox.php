<?php

namespace Blackbox\FilamentBlackbox\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Blackbox\FilamentBlackbox\FilamentBlackbox
 */
class FilamentBlackbox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Blackbox\FilamentBlackbox\FilamentBlackbox::class;
    }
}
