<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Audit as AuditContract;

/**
 * Writes an audit row by hand, the way an OLD row looks: it still holds values
 * (password, remember_token, ...) that the model's CURRENT owen-it rules
 * exclude. The row is of the CONFIGURED audit implementation, which does not
 * have to be owen-it's default Audit model.
 */
final class LegacyAudit
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function for(Model $model, array $old, array $new, string $event = 'updated'): Model & AuditContract
    {
        /** @var Model&AuditContract */
        return $model->audits()->create([
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'url' => 'http://localhost',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'pest',
            'tags' => null,
        ]);
    }
}
