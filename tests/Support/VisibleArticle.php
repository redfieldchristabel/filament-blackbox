<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A strict model with a visible allowlist: ONLY `title` is auditable, so an
 * audit key that is not on the hydrated model (e.g. `secret`, a dropped
 * column or legacy key) must be rejected too.
 */
class VisibleArticle extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'audited_posts';

    protected $guarded = [];

    protected $visible = ['title'];

    protected $auditStrict = true;
}
