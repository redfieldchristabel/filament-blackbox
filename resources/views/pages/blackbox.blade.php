<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-900 p-4 rounded-lg shadow">
            {{ $this->form }}
        </div>

        {{-- Main Container --}}
        <div class="relative ml-3 py-4">
            <div class="space-y-8">
                {{-- Empty State --}}
                @if($audits->isEmpty())
                    <div
                        class="flex flex-col items-center justify-center p-12 text-center bg-white border border-dashed border-gray-300 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                        <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">No audit logs found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">There are no activities matching your
                            current filters.</p>
                    </div>
                @endif

                @foreach ($audits as $audit)
                    <div class="relative border-l border-gray-200 dark:border-gray-700  pl-8"
                        wire:key="audit-row-{{ $audit->id }}">
                        <span
                            class="absolute -left-[5px] top-1 h-2.5 w-2.5 rounded-full bg-gray-200 ring-4 ring-white dark:bg-gray-700"></span>

                        <div class="mb-2 text-sm text-gray-400 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <span>{{ $audit->created_at->diffForHumans() }} &bull;
                                    {{ $audit->user->name ?? 'System' }}</span>

                                {{-- Resource Badge --}}
                                @php
                                    $badgeUrl = $this->getBadgeUrl($audit);
                                    // This now gets the full class string from your Enum
                                    $badgeClasses = $this->getBadgeColor($audit); 
                                @endphp

                                <a @if($badgeUrl) href="{{ $badgeUrl }}" @endif
                                    class="fi-badge flex items-center justify-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset transition-colors {{ $badgeClasses }} {{ $badgeUrl ? 'hover:opacity-75' : 'cursor-default' }}">
                                    {{ $this->getBadgeLabel($audit) }}
                                </a>
                            </div>
                        </div>

                        <livewire:blackbox::audit-item :audit="$audit" key="audit-item-{{ $audit->id }}" />

                        {{-- Collapsible Container --}}
                        {{-- <div x-data="{ expanded: false }"
                            class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 transition-all">
                            <div class="relative">
                                <div :class="!expanded ? 'max-h-70 overflow-hidden' : ''"
                                    class="transition-all duration-300">
                                    <livewire:redfieldchristabel /filament-blackbox::audit-item :audit="$audit"
                                        key="audit-item-{{ $audit->id }}" />
                                    <div x-show="!expanded"
                                        class="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-white dark:from-gray-800 to-transparent pointer-events-none">
                                    </div>
                                </div>

                                <div class="mt-2 flex justify-center">
                                    <button @click="expanded = !expanded" type="button"
                                        class="text-xs font-semibold uppercase tracking-wider text-primary-600 hover:text-primary-500 dark:text-primary-400 flex items-center gap-1">
                                        <span x-text="expanded ? 'Show Less' : 'Show Details'"></span>
                                        <svg :class="expanded ? 'rotate-180' : ''" class="w-4 h-4 transition-transform"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div> --}}
                    </div>
                @endforeach

                <div class="pl-8 pt-4">
                    @if ($audits->hasMorePages())
                        <div class="pl-8 pt-4" x-intersect.threshold.50="$wire.loadMore()"
                            wire:key="sentinel-{{ $perPage }}">
                            <div wire:loading wire:target="loadMore" class="text-sm text-gray-400 italic">
                                Fetching more records...
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>