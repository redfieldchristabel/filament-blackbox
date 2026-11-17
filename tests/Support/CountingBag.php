<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use OwenIt\Auditing\Contracts\Auditable;

/**
 * An ArrayBag that counts how often the exclusion rules are COMPUTED:
 * `getCreatedAtColumn()` is only read while the timestamp opt-out is applied
 * inside the rule computation — never while the precomputed list is reused —
 * so its call count proves how many rule computations a lookup path made.
 */
class CountingBag extends ArrayBag implements Auditable
{
    public static int $ruleComputations = 0;

    public function getCreatedAtColumn(): ?string
    {
        self::$ruleComputations++;

        return parent::getCreatedAtColumn();
    }
}
