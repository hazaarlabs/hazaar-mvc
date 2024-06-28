<?php

namespace Hazaar\DBI2\DBD\Interfaces\SQL;

interface StoredFunction
{
    /**
     * FUNCTIONS.
     */

    /**
     * List defined functions.
     *
     * @return array<int,array<mixed>|string>|false
     */
    public function listFunctions(?string $schemaName = null, bool $includeParameters = false): array|false;

    /**
     * @return array<int, array<string>>|false
     */
    public function describeFunction(string $name, ?string $schemaName = null): array|false;

    /**
     * Create a new database function.
     *
     * @param mixed $name The name of the function to create
     * @param mixed $spec A function specification.  This is basically the array returned from describeFunction()
     *
     * @return bool
     */
    public function createFunction($name, $spec);

    /**
     * Remove a function from the database.
     *
     * @param string                    $name     The name of the function to remove
     * @param null|array<string>|string $argTypes the argument list of the function to remove
     * @param bool                      $cascade  Whether to perform a DROP CASCADE
     */
    public function dropFunction(
        string $name,
        null|array|string $argTypes = null,
        bool $cascade = false,
        bool $ifExists = false
    ): bool;
}
