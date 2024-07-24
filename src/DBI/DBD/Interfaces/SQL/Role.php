<?php

namespace Hazaar\DBI\DBD\Interfaces\SQL;

interface Role
{
    /**
     * @return array<int, array<string>>|false
     */
    public function listUsers(): array|false;

    /**
     * @return array<int, array<string>>|false
     */
    public function listGroups(): array|false;

    /**
     * @param array<string> $privileges
     */
    public function createRole(string $name, ?string $password = null, array $privileges = []): bool;

    public function dropRole(string $name, bool $ifExists = false): bool;

    public function grantRole(string $role, string $to): bool;

    public function revokeRole(string $role, string $from): bool;
}
