<?php

namespace Hazaar\DBI\DBD\Traits\SQL;

trait View
{
    /**
     * Retrieves a list of views from the database.
     *
     * @return array<int, array<string>>|false an array of views, or null if no views are found
     */
    public function listViews(): array
    {
        return [];
    }

    /**
     * Retrieves the description of a database view.
     *
     * @param string $name the name of the view
     *
     * @return array<int, array<string>>|false the description of the view as an associative array, or null if the view does not exist
     */
    public function describeView($name): array|false
    {
        return false;
    }

    public function createView(string $name, mixed $content): bool
    {
        return false;
    }

    public function viewExists(string $viewName): bool
    {
        return false;
    }

    public function dropView(string $name, bool $cascade = false, bool $ifExists = false): bool
    {
        return false;
    }
}
