<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Audit as AuditTrait;
use OwenIt\Auditing\Contracts\Audit as AuditContract;

/**
 * A configured `audit.implementation` that implements owen-it's Audit contract
 * WITHOUT extending owen-it's default Audit model.
 */
class TaggedAudit extends Model implements AuditContract
{
    use AuditTrait;

    protected $table = 'audits';

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
    ];
}
