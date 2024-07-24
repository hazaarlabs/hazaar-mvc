<?php

namespace Hazaar\DBI\DBD\Interfaces\SQL;

interface Trigger
{
    /**
     * List defined triggers.
     *
     * @param string $schemaName Optional: schema name.  If not supplied the current schemaName is used.
     *
     * @return array<int,array<string>>|false
     */
    public function listTriggers(?string $tableName = null, ?string $schemaName = null): array|false;

    /**
     * Describe a database trigger.
     *
     * This will return an array as there can be multiple triggers with the same name but with different attributes
     *
     * @param string $schemaName Optional: schemaName name.  If not supplied the current schemaName is used.
     *
     * @return array<int, array<string>>|false
     */
    public function describeTrigger(string $triggerName, ?string $schemaName = null): array|false;

    /**
     * Summary of createTrigger.
     *
     * @param string $tableName The table on which the trigger is being created
     * @param mixed  $spec      The spec of the trigger.  Basically this is the array returned from describeTriggers()
     */
    public function createTrigger(string $triggerName, string $tableName, mixed $spec = []): bool;

    /**
     * Drop a trigger from a table.
     *
     * @param string $tableName The name of the table to remove the trigger from
     * @param bool   $cascade   Whether to drop CASCADE
     */
    public function dropTrigger(string $triggerName, string $tableName, bool $cascade = false, bool $ifExists = false): bool;
}
