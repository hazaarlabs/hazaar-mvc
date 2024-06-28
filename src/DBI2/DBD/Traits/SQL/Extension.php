<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait Extension
{
    /**
     * @return array<int, array<string>>|false
     */
    public function listExtensions(): array|false
    {
        return false;
    }

    public function createExtension(string $name): bool
    {
        return false;
    }

    public function dropExtension(string $name, bool $ifExists = false): bool
    {
        return false;
    }
}
