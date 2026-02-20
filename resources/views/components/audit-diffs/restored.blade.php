<div class="p-3 bg-white dark:bg-zinc-900 rounded-lg border border-blue-100 dark:border-blue-900/30 shadow-sm">
    <div class="text-xs font-semibold uppercase text-blue-500 dark:text-blue-400 mb-2 flex items-center gap-1.5" title="Restored Attribute">
        <x-heroicon-m-arrow-path class="w-4 h-4"/>
        <span>{{ \Illuminate\Support\Str::headline($item['attribute']) }}</span>
    </div>

    <div class="text-sm">
        <div class="text-xs text-gray-400 mb-0.5">Restored State</div>
        <div class="font-mono text-green-600 bg-green-50 dark:bg-green-900/20 dark:text-green-400 px-2 py-1 rounded break-all">
            {{ is_array($item['new']) || is_object($item['new'])
                ? json_encode($item['new'], JSON_PRETTY_PRINT)
                : $item['new'] ?? 'null' }}
        </div>
    </div>
</div>
