<div class="p-3 bg-white dark:bg-zinc-900 rounded-lg border border-gray-100 dark:border-none shadow">
    <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-2">
        {{ \Illuminate\Support\Str::headline($item['attribute']) }}
    </div>

    <div class="flex items-center gap-3 text-sm">
        <div class="flex-1 min-w-0">
            <div class="text-xs text-gray-400 mb-0.5">Current</div>
            <div class="font-mono text-red-600 bg-red-50 dark:bg-red-900/20 dark:text-red-400 px-2 py-1 rounded break-all">
                {{ is_array($item['current']) || is_object($item['current'])
                    ? json_encode($item['current'], JSON_PRETTY_PRINT)
                    : $item['current'] ?? 'null' }}
            </div>
        </div>

        <div class="flex-shrink-0 text-gray-400 dark:text-gray-500">
            <x-heroicon-m-arrow-right class="w-5 h-5"/>
        </div>

        <div class="flex-1 min-w-0">
            <div class="text-xs text-gray-400 mb-0.5">New</div>
            <div class="font-mono text-green-600 bg-green-50 dark:bg-green-900/20 dark:text-green-400 px-2 py-1 rounded break-all">
                {{ is_array($item['new']) || is_object($item['new'])
                    ? json_encode($item['new'], JSON_PRETTY_PRINT)
                    : $item['new'] ?? 'null' }}
            </div>
        </div>
    </div>
</div>
