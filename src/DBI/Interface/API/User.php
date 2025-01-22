<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

interface User
{
    /**
     * List all users in the database.
     *
     * @return array<mixed>
     */
    public function listUsers(): array;

    /**
     * Create a new user in the database.
     *
     * @param string        $name       the name of the user to create
     * @param null|string   $password   The password for the user.  If null, a random password will be generated.
     * @param array<string> $privileges an array of privileges to assign to the user
     */
    public function createUser(string $name, ?string $password = null, array $privileges = []): bool;

    /**
     * Drop a user from the database.
     *
     * @param string $name     the name of the user to drop
     * @param bool   $ifExists if true, the user will only be dropped if it exists
     */
    public function dropUser(string $name, bool $ifExists = false): bool;

    /**
     * Change the password for a user.
     *
     * @param array<string>|string $role the role to grant
     * @param string               $to   the user to grant the role to
     * @param string               $on   the database to grant the role on
     */
    public function grant(array|string $role, string $to, string $on): bool;

    /**
     * Revoke a role from a user.
     *
     * @param array<string>|string $role the role to revoke
     * @param string               $from the user to revoke the role from
     * @param string               $on   the database to revoke the role on
     */
    public function revoke(array|string $role, string $from, string $on): bool;
}
