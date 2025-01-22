<?php

namespace Hazaar\DBI\DBD\Traits\SQL;

trait Group
{
    /**
     * @return array<int, array<string>>|false
     */
    public function listGroups(): array
    {
        return [];
    }

    public function createGroup(string $name): bool
    {
        return false;
    }

    public function dropGroup(string $name, bool $ifExists = false): bool
    {
        return false;
    }

    public function groupExists(string $name): bool
    {
        return false;
    }

    public function addToGroup(string $user, string $group): bool
    {
        return false;
    }

    public function removeFromGroup(string $user, string $group): bool
    {
        return false;
    }
}
