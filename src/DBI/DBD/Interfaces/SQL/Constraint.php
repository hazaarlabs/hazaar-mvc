<?php

namespace Hazaar\DBI\DBD\Interfaces\SQL;

interface Constraint
{
    /**
     * @return array<int, array<string>>|false
     */
    public function listConstraints(
        ?string $tableName = null,
        ?string $type = null,
        bool $invertType = false
    ): array|false;

    public function addConstraint(string $constraintName, mixed $info): bool;

    public function dropConstraint(string $constraintName, string $tableName, bool $cascade = false, bool $ifExists = false): bool;
}
