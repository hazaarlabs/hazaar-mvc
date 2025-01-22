<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

interface Extension
{
    /**
     * List all available extensions.
     *
     * @return array<mixed>
     */
    public function listExtensions(): array;

    /**
     * Check if an extension exists.
     *
     * @param string $name the name of the extension to check
     */
    public function extensionExists(string $name): bool;

    /**
     * Create an extension.
     *
     * @param string $name the name of the extension to create
     */
    public function createExtension(string $name): bool;

    /**
     * Drop an extension.
     *
     * @param string $name     the name of the extension to drop
     * @param bool   $ifExists if true, do not throw an error if the extension does not exist
     */
    public function dropExtension(string $name, bool $ifExists = false): bool;
}
