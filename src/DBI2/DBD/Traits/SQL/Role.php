<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait Role
{
    /**
     * @return array<int, array<string>>|false
     */
    public function listUsers(): array|false
    {
        return false;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listGroups(): array|false
    {
        return false;
    }

    /**
     * @param array<string> $privileges
     */
    public function createRole(string $name, ?string $password = null, array $privileges = []): bool
    {
        return false;
    }

    public function dropRole(string $name, bool $ifExists = false): bool
    {
        return false;
    }

    public function grantRole(string $role, string $to): bool
    {
        return false;
    }

    public function revokeRole(string $role, string $from): bool
    {
        return false;
    }
}
