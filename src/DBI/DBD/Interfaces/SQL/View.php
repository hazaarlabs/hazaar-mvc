<?php

namespace Hazaar\DBI\DBD\Interfaces\SQL;

interface View
{
    /**
     * @return array<int, array<string>>|false
     */
    public function listViews(): array|false;

    /**
     * @return array<int, array<string>>|false
     */
    public function describeView(string $name): array|false;

    public function createView(string $name, mixed $content): bool;

    public function viewExists(string $viewName): bool;

    public function dropView(string $name, bool $cascade = false, bool $ifExists = false): bool;
}
