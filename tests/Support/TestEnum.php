<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

/**
 * A backed enum: owen-it keeps enum CURRENT values auditable.
 */
enum TestEnum: string
{
    case Spades = 'spades';
    case Hearts = 'hearts';
}
