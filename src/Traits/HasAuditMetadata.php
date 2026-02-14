<?php

namespace Blackbox\FilamentBlackbox\Traits;

use OwenIt\Auditing\Models\Audit;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HasAuditMetadata
{
    public function getBadgeColor(Audit $audit): string
    {
        /** @var array<string, mixed> $resources */
        $resources = config('blackbox.resources', []);

        $resourceConfig = $resources[$audit->auditable_type] ?? [];

        // Level 6: Ensure we return a string
        return (string) ($resourceConfig['badge_class'] ?? $resources['default']['badge_class'] ?? 'fi-color-gray');
    }

    public function getBadgeLabel(Audit $audit): string
    {
        /** @var array<string, mixed> $resources */
        $resources = config('blackbox.resources', []);

        $resourceConfig = $resources[$audit->auditable_type] ?? [];

        $label = $resourceConfig['label'] ?? class_basename($audit->auditable_type);

        return $label . ' #' . $audit->auditable_id;
    }

    public function getBadgeUrl(Audit $audit): ?string
    {
        // 1. Check if the model still exists to prevent 404s
        if ($audit->auditable === null) {
            return null;
        }

        /** @var array<string, mixed> $resources */
        $resources = config('blackbox.resources', []);

        $config = $resources[$audit->auditable_type] ?? [];
        $resource = $config['resource'] ?? null;

        if (!$resource || !class_exists((string) $resource)) {
            return null;
        }

        /** @var \Filament\Resources\Resource $resource */
        try {
            // Try 'edit' first
            return $resource::getUrl('edit', ['record' => $audit->auditable_id]);
        } catch (Throwable) {
            try {
                // Fallback to 'view'
                return $resource::getUrl('view', ['record' => $audit->auditable_id]);
            } catch (Throwable) {
                return null;
            }
        }
    }
}