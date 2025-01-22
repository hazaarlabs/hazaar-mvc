<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interfaces\API;

interface Schema
{
    /**
     * Get the schema name.
     */
    public function getSchemaName(): string;

    /**
     * Check if the schema exists.
     *
     * @param null|string $schemaName the schema name
     */
    public function schemaExists(?string $schemaName = null): bool;

    /**
     * Create the schema.
     *
     * @param null|string $schemaName the schema name
     */
    public function createSchema(?string $schemaName = null): bool;
}
