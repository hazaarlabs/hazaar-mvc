<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

interface Trigger
{
    /**
     * List all triggers in the database or for a specific table.
     *
     * @return array<mixed>
     */
    public function listTriggers(?string $tableName = null): array;

    /**
     * Check if a trigger exists.
     *
     * @param string $triggerName the name of the trigger to check
     * @param string $tableName   The n`ame of the table to check the
     */
    public function triggerExists(string $triggerName, string $tableName): bool;

    /**
     * Describe a trigger.
     *
     * @param string $triggerName the name of the trigger to describe
     *
     * @return array<mixed>|false
     */
    public function describeTrigger(string $triggerName): array|false;

    /**
     * Create a trigger.
     *
     * @param string $triggerName the name of the trigger to create
     * @param string $tableName   the name of the table to create the trigger on
     * @param mixed  $spec        the trigger specification
     */
    public function createTrigger(string $triggerName, string $tableName, mixed $spec = []): bool;

    /**
     * Drop a trigger.
     *
     * @param string $triggerName the name of the trigger to drop
     * @param string $tableName   The name of the table to drop the
     */
    public function dropTrigger(string $triggerName, string $tableName, bool $ifExists = false, bool $cascade = false): bool;
}
