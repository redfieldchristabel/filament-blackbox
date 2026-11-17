<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * An in-memory auditable model: used to check owen-it's non scalar exclusions
 * (arrays and objects) against the model's CURRENT attribute values.
 */
class ArrayBag extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'array_bags';

    protected $guarded = [];
}
