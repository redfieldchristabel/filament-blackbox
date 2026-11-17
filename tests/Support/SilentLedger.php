<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

/**
 * A model whose auditRelations() is NOT callable from the outside (protected):
 * Blackbox must treat it as absent instead of fataling through Model::__call.
 */
class SilentLedger extends AuditedPost
{
    protected function auditRelations(): array
    {
        return ['comments'];
    }
}
