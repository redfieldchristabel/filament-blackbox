<?php

use Livewire\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Models\Audit;
use OwenIt\Auditing\Contracts\Auditable;

new class extends Component {
    use InteractsWithForms;
    use InteractsWithInfolists;

    public Model $record;

    public int $perPage = 3;

    public function loadMore()
    {
        $this->perPage += 3;
    }

    public function getAuditsProperty()
    {
        // 1. Type safety check for PHPStan
        if (!($this->record instanceof Auditable)) {
            return null;
        }

        $modelClass = get_class($this->record);
        $nativeAudits = $this->record->audits();

        // 2. Fetch relations from config instead of model method
        $resourceConfig = config("blackbox.resources.{$modelClass}");
        $relations = $resourceConfig['relations'] ?? [];

        if (empty($relations)) {
            return $nativeAudits->latest()->take($this->perPage)->get();
        }

        // Prepare relations to load (Eager Loading to prevent N+1)
        $relationsToLoad = [];
        $traversalKeys = array_keys($relations);

        foreach ($relations as $relationName => $nestedPath) {
            if (empty($nestedPath)) {
                $relationsToLoad[] = $relationName;
                continue;
            }

            // Support comma separated nested relations from config
            $nestedRelations = explode(',', $nestedPath);
            foreach ($nestedRelations as $nested) {
                $nested = trim($nested);
                $relationsToLoad[] = $nested ? "{$relationName}.{$nested}" : $relationName;
            }
        }

        // 3. Prevent N+1 by loading missing relations
        $this->record->loadMissing(array_unique($relationsToLoad));

        $relatedAuditsMap = [];

        // 4. Map related models to their Morph Classes and Keys
        foreach ($traversalKeys as $relationName) {
            $relatedModels = $this->record->$relationName;

            if ($relatedModels instanceof Model) {
                $relatedAuditsMap[$relatedModels->getMorphClass()][] = $relatedModels->getKey();
            } elseif ($relatedModels instanceof \Illuminate\Support\Collection) {
                foreach ($relatedModels as $model) {
                    if ($model instanceof Model) {
                        $relatedAuditsMap[$model->getMorphClass()][] = $model->getKey();
                    }
                }
            }
        }

        // 5. Execute unified Audit query
        return Audit::query()
            ->where(function ($query) use ($relatedAuditsMap) {
                $query->where('auditable_type', $this->record->getMorphClass())
                    ->where('auditable_id', $this->record->getKey());

                foreach ($relatedAuditsMap as $type => $ids) {
                    // Using count() to satisfy PHPStan strict rules
                    if (count($ids) > 0) {
                        $query->orWhere(function ($subQuery) use ($type, $ids) {
                            $subQuery->where('auditable_type', $type)
                                ->whereIn('auditable_id', $ids);
                        });
                    }
                }
            })
            ->latest()
            ->take($this->perPage)
            ->get();
    }

    public function getBadgeColor(Audit $audit): string
    {
        $resourceConfig = config("blackbox.resources.{$audit->auditable_type}");

        // Return the specific color, or the default gray from config
        return $resourceConfig['color'] ?? config('blackbox.resources.default.color', 'fi-badge-color-gray');
    }

    public function getBadgeLabel(Audit $audit): string
    {
        $resourceConfig = config("blackbox.resources.{$audit->auditable_type}");

        // Use the label from config if exists, otherwise fallback to class basename
        $label = $resourceConfig['label'] ?? class_basename($audit->auditable_type);

        return $label . ' #' . $audit->auditable_id;
    }


    public function getBadgeUrl(Audit $audit): ?string
    {
        $config = config("blackbox.resources.{$audit->auditable_type}");
        $resource = $config['resource'] ?? null;

        if (!$resource || !class_exists($resource)) {
            return null;
        }

        /** @var \Filament\Resources\Resource $resource */
        try {
            // 1. Try to generate the 'edit' page URL
            return $resource::getUrl('edit', ['record' => $audit->auditable_id]);
        } catch (\Throwable $th) {
            try {
                // 2. Fallback: Try to generate the 'view' page URL
                return $resource::getUrl('view', ['record' => $audit->auditable_id]);
            } catch (\Throwable $th) {
                // 3. If neither exists or user lacks access, return null
                return null;
            }
        }
    }
};
?>

<div x-data="{ ready: false }"
    x-init="$el.closest('.fi-modal-content')?.scrollTo(0,0); setTimeout(() => ready = true, 500)"
    class="relative border-l border-gray-200 dark:border-gray-700 ml-3 py-4">
    <!-- Loading Spinner (Inline styles for FOUC prevention) -->
    <div x-show="!ready" style="display: flex; justify-content: center; align-items: center; min-height: 200px;">
        <x-filament::loading-indicator style="height: 3rem; width: 3rem;" class="text-gray-500" />
    </div>

    <!-- Main Content -->
    <div x-show="ready" x-cloak class="space-y-8">
        @forelse ($this->audits as $audit)
            <div class="relative pl-8">
                <!-- Timeline Dot -->
                <span
                    class="absolute -left-[5px] top-1 h-2.5 w-2.5 rounded-full bg-gray-200 ring-4 ring-white dark:bg-gray-700 dark:ring-gray-900"></span>

                <!-- Date/Time Header -->
                <div class="mb-2 text-sm font-normal text-gray-400 dark:text-gray-500">
                    {{ $audit->created_at->diffForHumans() }}
                    <span class="mx-1">&bull;</span>
                    <span class="text-gray-500 dark:text-gray-400 font-medium">{{ $audit->user->name ?? 'System' }}</span>
                    <span class="mx-1">&bull;</span>

                    @if($url = $this->getBadgeUrl($audit))
                        <a href="{{ $url }}" wire:navigate
                            class="text-xs px-2 py-0.5 rounded hover:opacity-80 transition-opacity {{ $this->getBadgeColor($audit) }}">
                            {{ $this->getBadgeLabel($audit) }}
                        </a>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded {{ $this->getBadgeColor($audit) }}">
                            {{ $this->getBadgeLabel($audit) }}
                        </span>
                    @endif
                </div>

                <!-- Card Content -->
                <div x-data="{ expanded: false }"
                    class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div :class="expanded ? '' : 'max-h-60 overflow-hidden relative'">
                        <livewire:blackbox::audit-item :audit="$audit" :key="$audit->id" />
                        <div x-show="!expanded"
                            class="absolute bottom-0 left-0 w-full h-16 bg-gradient-to-t from-white dark:from-gray-800 to-transparent pointer-events-none">
                        </div>
                    </div>

                    <div class="mt-2 flex justify-center">
                        <button type="button" @click="expanded = !expanded"
                            class="text-xs text-primary-600 hover:text-primary-500 font-medium flex items-center gap-1 focus:outline-none">
                            <span x-text="expanded ? 'Show Less' : 'Show More'"></span>
                            <x-filament::icon icon="heroicon-m-chevron-down"
                                class="h-3 w-3 transition-transform duration-200"
                                x-bind:class="expanded ? 'rotate-180' : ''" />
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="pl-8 text-sm text-gray-500 dark:text-gray-400 italic">
                No audit history found.
            </div>
        @endforelse

        @if($this->audits->count() >= $perPage)
            <div class="pl-8 pt-4" x-intersect.threshold.50="$wire.loadMore()">
                <div wire:loading wire:target="loadMore" class="text-gray-400 text-sm italic">
                    Loading more...
                </div>

                <div wire:loading.remove wire:target="loadMore">
                    <x-filament::button wire:click="loadMore" color="gray" size="sm">
                        Load More
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>
</div>