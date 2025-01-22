<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

interface StoredFunction
{
    /**
     * List functions in the database.
     *
     * @param bool $includeParameters include function parameters in the list
     *
     * @return array<mixed>
     */
    public function listFunctions(bool $includeParameters = false): array;

    /**
     * Check if a function exists in the database.
     *
     * @param string      $functionName the name of the function to check
     * @param null|string $argTypes     the argument types of the function
     */
    public function functionExists(string $functionName, ?string $argTypes = null): bool;

    /**
     * Describe a function in the database.
     *
     * @param string $name the name of the function to describe
     *
     * @return array<mixed>|false
     */
    public function describeFunction(string $name): array|false;

    /**
     * Create a function in the database.
     *
     * @param string $name the name of the function to create
     * @param mixed  $spec the function specification
     */
    public function createFunction($name, $spec): bool;

    /**
     * Drop a function from the database.
     *
     * @param string                   $name     the name of the function to drop
     * @param null|array<mixed>|string $argTypes the argument types of the function
     * @param bool                     $cascade  drop dependent objects
     * @param bool                     $ifExists drop only if the function exists
     */
    public function dropFunction(string $name, null|array|string $argTypes = null, bool $cascade = false, bool $ifExists = false): bool;
}
