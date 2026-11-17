<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class AuditedPost extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'audited_posts';

    protected $guarded = [];

    public function comments(): HasMany
    {
        return $this->hasMany(AuditedComment::class, 'audited_post_id');
    }
}
