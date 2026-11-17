<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A soft-deletable auditable model: Recover/Revert must respect the record's
 * own soft-delete state instead of only what the action button showed.
 */
class TrashedArticle extends Model implements Auditable
{
    use AuditableTrait;
    use SoftDeletes;

    protected $table = 'trashed_articles';

    protected $guarded = [];
}
