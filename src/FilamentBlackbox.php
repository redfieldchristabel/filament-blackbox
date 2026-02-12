<?php

namespace Blackbox\FilamentBlackbox;

use OwenIt\Auditing\Models\Audit;
use Illuminate\Pagination\CursorPaginator;

class FilamentBlackbox
{
    /**
     * @param  array<string, mixed>  $filters
     * @return CursorPaginator
     */
    public function getAudits(array $filters = [], int $perPage = 10): CursorPaginator
    {
        // Get the configured audit model class from the audit package
        $auditModel = config('audit.implementation', \OwenIt\Auditing\Models\Audit::class);

        // Start the query using the resolved class
        $query = $auditModel::query()->with(['user', 'auditable']);

        if (! empty($filters['users'])) {
            $query->whereIn('user_id', $filters['users']);
        }

        if (! empty($filters['events'])) {
            $query->whereIn('event', $filters['events']);
        }

        if (! empty($filters['resource_types'])) {
            $query->whereIn('auditable_type', $filters['resource_types']);
        }

        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_until'])) {
            $query->where('created_at', '<=', $filters['created_until']);
        }

        return $query->latest()->cursorPaginate($perPage);
    }
}
