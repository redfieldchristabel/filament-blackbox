<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
    <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-2">
        {{ \Illuminate\Support\Str::headline($item['attribute']) }}
    </div>

    <div class="text-sm">
        <div class="text-xs text-gray-400 mb-0.5">Value</div>
        <div class="font-mono text-green-600 bg-green-50 dark:bg-green-900/20 dark:text-green-400 px-2 py-1 rounded break-all">
            {{ is_array($item['new']) || is_object($item['new'])
                ? json_encode($item['new'], JSON_PRETTY_PRINT)
                : $item['new'] ?? 'null' }}
        </div>
    </div>
</div>
