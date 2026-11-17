<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A model whose setter for an ALLOWED field (`name`) also dirties an EXCLUDED
 * one (`api_token`): Revert/Recover must refuse, never show the value and
 * never save it.
 */
class MutatingAccount extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'secure_accounts';

    protected $guarded = [];

    protected $auditExclude = ['api_token'];

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;

        // A side effect of an allowed setter: an excluded attribute changes.
        $this->attributes['api_token'] = 'token-for-' . $value;
    }
}
