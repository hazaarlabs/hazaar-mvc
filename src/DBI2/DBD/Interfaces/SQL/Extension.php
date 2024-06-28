<?php

namespace Hazaar\DBI2\DBD\Interfaces\SQL;

interface Extension
{
    /**
     * @return array<string>|false
     */
    public function listExtensions(): array|false;

    public function createExtension(string $name): bool;

    public function dropExtension(string $name, bool $ifExists = false): bool;
}
