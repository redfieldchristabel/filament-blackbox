<?php

namespace Blackbox\FilamentBlackbox\Support;

use Illuminate\Database\Eloquent\Model;
use ReflectionMethod;

/**
 * Which related models' audits are pulled into a record's timeline.
 *
 * Source, in order: `blackbox.resources.<Model>.relations`, then the model's
 * own `auditRelations()` method when the config key is absent. Both accept
 * associative entries (['rel' => 'nested1,nested2']) and indexed ones (['rel']).
 */
final class AuditRelations
{
    /**
     * @return array<string, string> relation name => comma separated nested relations ('' for none)
     */
    public static function for(Model $record): array
    {
        /** @var array<string, mixed> $resources */
        $resources = (array) config('blackbox.resources', []);

        $resource = $resources[$record::class] ?? $resources[$record->getMorphClass()] ?? [];

        $relations = is_array($resource) ? ($resource['relations'] ?? null) : null;

        // A real, PUBLIC method: method_exists alone would fatal on an
        // inaccessible (e.g. protected) auditRelations(); is_callable alone
        // would fatal on a missing one (Eloquent's __call makes every method
        // name "callable" and then refuses it). The reflection check answers
        // exactly the visibility question is_callable cannot settle here.
        if ($relations === null && method_exists($record, 'auditRelations')) {
            $method = new ReflectionMethod($record, 'auditRelations');

            if ($method->isPublic()) {
                $relations = $record->auditRelations();
            }
        }

        return self::normalize(is_array($relations) ? $relations : []);
    }

    /**
     * @param  array<int|string, mixed>  $relations
     * @return array<string, string>
     */
    public static function normalize(array $relations): array
    {
        $normalized = [];

        foreach ($relations as $key => $value) {
            if (is_int($key)) {
                // Indexed entry: the value is the relation name.
                if (is_string($value) && trim($value) !== '') {
                    $normalized[trim($value)] ??= '';
                }

                continue;
            }

            $nested = is_array($value) ? implode(',', array_map('strval', $value)) : (string) $value;

            $normalized[$key] = trim($nested);
        }

        return $normalized;
    }
}
