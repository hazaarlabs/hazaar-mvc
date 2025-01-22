<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

interface Group
{
    /**
     * List all groups.
     *
     * @return array<string>
     */
    public function listGroups(): array;

    /**
     * Create a new group.
     *
     * @param string $groupName the name of the group to create
     */
    public function createGroup(string $groupName): bool;

    /**
     * Drop a group.
     *
     * @param string $groupName the name of the group to drop
     */
    public function dropGroup(string $groupName): bool;

    /**
     * Add a role to a group.
     *
     * @param string $roleName       the name of the role to add
     * @param string $parentRoleName the name of the group to add the role to
     */
    public function addToGroup(string $roleName, string $parentRoleName): bool;

    /**
     * Remove a role from a group.
     *
     * @param string $roleName       the name of the role to remove
     * @param string $parentRoleName the name of the group to remove the role from
     */
    public function removeFromGroup(string $roleName, string $parentRoleName): bool;
}
