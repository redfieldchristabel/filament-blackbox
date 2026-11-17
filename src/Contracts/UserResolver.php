<?php

namespace Blackbox\FilamentBlackbox\Contracts;

/**
 * Finds the users shown in the Blackbox user filter.
 *
 * Set a class-string implementing this contract in `blackbox.users.resolver`
 * to replace the default (column search on the auth provider's user model).
 * The class is resolved through the container.
 */
interface UserResolver
{
    /**
     * Users matching the search text, as [id => label]. Keep it bounded: this
     * is called while the person types.
     *
     * @return array<int|string, string>
     */
    public function search(string $search): array;

    /**
     * Labels for the given user ids, as [id => label].
     *
     * @param  array<int, int|string>  $ids
     * @return array<int|string, string>
     */
    public function labels(array $ids): array;
}
