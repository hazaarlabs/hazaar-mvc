<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

interface View
{
    /**
     * List all views in the database.
     *
     * @return array<mixed>
     */
    public function listViews(): array;

    /**
     * Check if a view exists.
     *
     * @param string $viewName The name of the view to check
     */
    public function viewExists(string $viewName): bool;

    /**
     * Describe a view.
     *
     * @return array<mixed>|false
     */
    public function describeView(string $name): array|false;

    /**
     * Create a view.
     *
     * @param string $name    The name of the view to create
     * @param mixed  $content The content of the view
     */
    public function createView(string $name, mixed $content, bool $replace = false): bool;

    /**
     * Drop a view.
     *
     * @param string $name     The name of the view to drop
     * @param bool   $ifExists If true, the view will only be dropped if it exists
     * @param bool   $cascade  If true, the view will be dropped even if it has dependencies
     */
    public function dropView(string $name, bool $ifExists = false, bool $cascade = false): bool;
}
