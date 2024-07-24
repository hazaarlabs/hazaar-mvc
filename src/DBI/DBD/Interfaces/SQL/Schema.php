<?php

namespace Hazaar\DBI\DBD\Interfaces\SQL;

interface Schema
{
    public function getSchemaName(): string;

    public function schemaExists(?string $schemaName = null): bool;

    public function createSchema(string $name): bool;
}
