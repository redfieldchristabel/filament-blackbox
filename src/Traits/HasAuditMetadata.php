<?php

namespace Blackbox\FilamentBlackbox\Traits;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Audit as AuditContract;
use Throwable;

trait HasAuditMetadata
{
    /**
     * The configured `audit.implementation` model — any Eloquent model
     * implementing owen-it's Audit contract, not only the default model.
     *
     * @param  Model&AuditContract  $audit
     */
    public function getBadgeColor(Model $audit): string
    {
        /** @var array<string, mixed> $resources */
        $resources = config('blackbox.resources', []);

        $resourceConfig = $resources[$audit->getAttribute('auditable_type')] ?? [];

        // Level 6: Ensure we return a string
        return (string) ($resourceConfig['badge_class'] ?? $resources['default']['badge_class'] ?? 'fi-color-gray');
    }

    /**
     * @param  Model&AuditContract  $audit
     */
    public function getBadgeLabel(Model $audit): string
    {
        /** @var array<string, mixed> $resources */
        $resources = config('blackbox.resources', []);

        $resourceConfig = $resources[$audit->getAttribute('auditable_type')] ?? [];

        $label = $resourceConfig['label'] ?? class_basename($audit->getAttribute('auditable_type'));

        return $label . ' #' . $audit->getAttribute('auditable_id');
    }

    /**
     * @param  Model&AuditContract  $audit
     */
    public function getBadgeUrl(Model $audit): ?string
    {
        // 1. Check if the model still exists to prevent 404s (lazy-loads the
        //    auditable relation, the way `$audit->auditable` would)
        if ($audit->getRelationValue('auditable') === null) {
            return null;
        }

        /** @var array<string, mixed> $resources */
        $resources = config('blackbox.resources', []);

        $config = $resources[$audit->getAttribute('auditable_type')] ?? [];
        $resource = $config['resource'] ?? null;

        if (! $resource || ! class_exists((string) $resource)) {
            return null;
        }

        /** @var \Filament\Resources\Resource $resource */
        try {
            // Try 'edit' first
            return $resource::getUrl('edit', ['record' => $audit->getAttribute('auditable_id')]);
        } catch (Throwable) {
            try {
                // Fallback to 'view'
                return $resource::getUrl('view', ['record' => $audit->getAttribute('auditable_id')]);
            } catch (Throwable) {
                return null;
            }
        }
    }
}
