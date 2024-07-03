<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait Group
{
    /**
     * @return array<int, array<string>>|false
     */
    public function listGroups(): array|false
    {
        $sql = 'SELECT rolname FROM pg_roles WHERE rolcanlogin = false';

        return $this->query($sql)->fetchAll();
    }

    public function createGroup(string $name): bool
    {
        $sql = 'CREATE ROLE '
            .$this->queryBuilder->prepareValue($name);

        return false !== $this->exec($sql);
    }

    public function dropGroup(string $name, bool $ifExists = false): bool
    {
        $sql = 'DROP ROLE '.($ifExists ? 'IF EXISTS ' : '')
            .$this->queryBuilder->prepareValue($name);

        return false !== $this->exec($sql);
    }
}
