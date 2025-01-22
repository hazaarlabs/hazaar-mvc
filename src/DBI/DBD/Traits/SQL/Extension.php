<?php

namespace Hazaar\DBI\DBD\Traits\SQL;

trait Extension
{
    /**
     * @return array<int, array<string>>
     */
    public function listExtensions(): array
    {
        return [];
    }

    public function createExtension(string $name): bool
    {
        return false;
    }

    public function dropExtension(string $name, bool $ifExists = false): bool
    {
        return false;
    }

    public function extensionExists(string $name): bool
    {
        return false;
    }
}
