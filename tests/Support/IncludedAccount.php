<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A model with an `$auditInclude` allowlist: ONLY `name` is auditable, so any
 * other key on an (old) audit row — `password`, `api_token` — is hidden and
 * never restored.
 */
class IncludedAccount extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'secure_accounts';

    protected $guarded = [];

    protected $auditInclude = ['name'];
}
