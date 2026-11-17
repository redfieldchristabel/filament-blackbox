<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Audit as AuditTrait;
use OwenIt\Auditing\Contracts\Audit as AuditContract;

/**
 * A configured `audit.implementation` whose timestamp columns are NOT
 * created_at/updated_at: replicate() drops performed_at/performed_until, so a
 * filtered copy must carry them back under the model's real column names.
 */
class ScheduledAudit extends Model implements AuditContract
{
    use AuditTrait;

    protected $table = 'audits';

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    const CREATED_AT = 'performed_at';

    const UPDATED_AT = 'performed_until';
}
