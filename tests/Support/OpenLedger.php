<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A NON-strict owen-it model: `$hidden` does NOT exclude anything (exactly
 * like owen-it — hidden attributes only count in strict mode), so secrets a
 * non-strict model must never show or restore have to sit in `$auditExclude`.
 */
class OpenLedger extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'secure_accounts';

    protected $guarded = [];

    protected $hidden = ['api_token'];

    protected $auditExclude = ['password', 'remember_token'];
}
