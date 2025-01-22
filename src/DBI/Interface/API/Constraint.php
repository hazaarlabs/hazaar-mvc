<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

interface Constraint
{
    /**
     * List all constraints for a table.
     *
     * @param string $table      The table to list constraints for
     * @param string $type       The type of constraint to list.  If null, all constraints are listed.
     * @param bool   $invertType If true, the type is inverted.  This means that all constraints that are NOT of the specified type are returned.
     *
     * @return array<mixed>|false
     */
    public function listConstraints($table = null, $type = null, $invertType = false): array|false;

    /**
     * List all constraints for a table.
     *
     * @param string       $constraintName The name of the constraint to list
     * @param array<mixed> $info           The information to use to add the constraint
     */
    public function addConstraint(string $constraintName, array $info): bool;

    /**
     * Drop a constraint from a table.
     *
     * @param string $constraintName The name of the constraint to drop
     * @param string $tableName      The table to drop the constraint from
     * @param bool   $ifExists       If true, the constraint will only be dropped if it exists
     * @param bool   $cascade        If true, the constraint will be dropped with a cascade
     */
    public function dropConstraint(string $constraintName, string $tableName, bool $ifExists = false, bool $cascade = false): bool;
}
