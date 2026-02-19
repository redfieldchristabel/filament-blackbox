<div class="p-3 bg-white dark:bg-zinc-900 rounded-lg border border-gray-100 dark:border-gray-700">
    <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-2">
        {{ \Illuminate\Support\Str::headline($item['attribute']) }}
    </div>
    
    <div class="grid grid-cols-2 gap-4 text-sm">
        <!-- Added -->
        <div class="min-w-0">
            <div class="flex items-center gap-1 text-xs text-gray-400 mb-1">
                <x-heroicon-m-plus class="w-3 h-3 text-green-500" />
                <span>Added</span>
            </div>
            @if(empty($item['added']))
                <span class="text-gray-400 italic text-xs">-</span>
            @else
                <div class="flex flex-wrap gap-1">
                    @foreach((array)$item['added'] as $val)
                        <div  class="font-mono text-xs text-green-700 bg-green-100 dark:bg-green-900/30 dark:text-green-400 px-2 py-1 rounded">
                            {{ is_array($val) || is_object($val) ? json_encode($val) : $val }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Removed -->
        <div class="min-w-0">
            <div class="flex items-center gap-1 text-xs text-gray-400 mb-1">
                <x-heroicon-m-minus class="w-3 h-3 text-red-500" />
                <span>Removed</span>
            </div>
            @if(empty($item['removed']))
                <span class="text-gray-400 italic text-xs">-</span>
            @else
                <div class="flex flex-wrap gap-1">
                   @foreach((array)$item['removed'] as $val)
                        <div class="font-mono text-xs text-red-700 bg-red-100 dark:bg-red-900/30 dark:text-red-400 px-2 py-1 rounded">
                            {{ is_array($val) || is_object($val) ? json_encode($val) : $val }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
