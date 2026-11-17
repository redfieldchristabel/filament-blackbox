<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * An auditable model whose excluded field carries a newer `encrypted` cast: a
 * legacy plain-text value must never be formatted (decrypted) by display code.
 */
class SealedDiary extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'sealed_diaries';

    protected $guarded = [];

    protected $casts = [
        'secret_note' => 'encrypted',
    ];

    protected $auditExclude = ['secret_note'];
}
