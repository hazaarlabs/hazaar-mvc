<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait User
{
    /**
     * @return array<int, array<string>>|false
     */
    public function listUsers(): array|false
    {
        $sql = 'SELECT rolname FROM pg_roles WHERE rolcanlogin = true';

        return $this->query($sql)->fetchAll();
    }

    /**
     * @param array<string> $privileges
     */
    public function createUser(string $name, ?string $password = null, array $privileges = []): bool
    {
        $sql = 'CREATE ROLE '
            .$this->queryBuilder->quoteSpecial($name)
            .' WITH LOGIN';
        if (null !== $password) {
            $sql .= ' PASSWORD '.$this->queryBuilder->prepareValue($password);
        }
        if (!empty($privileges)) {
            $sql .= ' '.implode(' ', $privileges);
        }

        return false !== $this->exec($sql);
    }

    public function dropUser(string $name, bool $ifExists = false): bool
    {
        $sql = 'DROP ROLE '.($ifExists ? 'IF EXISTS ' : '')
            .$this->queryBuilder->quoteSpecial($name);

        return false !== $this->exec($sql);
    }
}
