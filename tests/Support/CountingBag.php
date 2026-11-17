<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use OwenIt\Auditing\Contracts\Auditable;

/**
 * An ArrayBag that counts how often the exclusion rules are COMPUTED:
 * `getCreatedAtColumn()` is only read while the timestamp opt-out is applied
 * inside the rule computation — never on a memo hit (the stamp re-fingerprints
 * OTHER rule inputs, but never this one) — so its call count proves cache hits
 * and recomputations.
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
