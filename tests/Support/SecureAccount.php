<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Strict owen-it model: hidden attributes and `api_token` are not audited.
 */
class SecureAccount extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'secure_accounts';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $auditStrict = true;

    protected $auditExclude = ['api_token'];
}
