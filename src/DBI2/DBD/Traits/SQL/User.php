<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait User
{
    /**
     * @return array<int, array<string>>
     */
    public function listUsers(): array
    {
        return [];
    }

    /**
     * @param array<string> $privileges
     */
    public function createUser(string $name, ?string $password = null, array $privileges = []): bool
    {
        return false;
    }

    public function dropUser(string $name, bool $ifExists = false): bool
    {
        return false;
    }
}
