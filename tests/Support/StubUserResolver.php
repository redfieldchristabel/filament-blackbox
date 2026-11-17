<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

use Blackbox\FilamentBlackbox\Contracts\UserResolver;

class StubUserResolver implements UserResolver
{
    public function search(string $search): array
    {
        return [7 => 'Stub ' . $search];
    }

    public function labels(array $ids): array
    {
        return array_combine($ids, array_map(fn ($id) => 'Stub #' . $id, $ids)) ?: [];
    }
}
