<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

/**
 * An audited post that declares its own timeline relations (indexed form).
 */
class AuditedArticle extends AuditedPost
{
    /**
     * @return array<int, string>
     */
    public function auditRelations(): array
    {
        return ['comments'];
    }
}
