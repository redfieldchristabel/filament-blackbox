<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class AuditedComment extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'audited_comments';

    protected $guarded = [];
}
