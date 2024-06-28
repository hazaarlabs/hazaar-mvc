<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD\Traits;

use Hazaar\DBI2\Interfaces\QueryBuilder;
use Hazaar\DBI2\QueryBuilder\SQL as SQLBuilder;

trait SQL
{
    private QueryBuilder $queryBuilder;

    public function initQueryBuilder(string $schema): void
    {
        $this->queryBuilder = new SQLBuilder($schema);
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function grant(array|string $privilege, string $object, string $to, ?string $schema = null): bool
    {
        if (is_array($privilege)) {
            $privilege = implode(', ', $privilege);
        }
        $sql = 'GRANT '.$privilege.' ON '.$this->queryBuilder->schemaName($object).' TO '.$to;
        if ($schema) {
            $sql .= ' WITH GRANT OPTION';
        }

        return false !== $this->exec($sql);
    }

    public function revoke(array|string $privilege, string $object, string $from, ?string $schema = null): bool
    {
        if (is_array($privilege)) {
            $privilege = implode(', ', $privilege);
        }
        $sql = 'REVOKE '.$privilege.' ON '.$this->queryBuilder->schemaName($object).' FROM '.$from;
        if ($schema) {
            $sql .= ' CASCADE';
        }

        return false !== $this->exec($sql);
    }

    public function createDatabase(string $name): bool
    {
        $sql = 'CREATE DATABASE '.$this->queryBuilder->quoteSpecial($name).';';
        $result = $this->query($sql);

        return true;
    }

    protected function fixValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param array<string> $info
     */
    protected function type(array $info): string
    {
        if (!($type = ake($info, 'data_type'))) {
            return 'character varying';
        }
        if ($array = ('[]' === substr($type, -2))) {
            $type = substr($type, 0, -2);
        }

        return $type.(ake($info, 'length') ? '('.$info['length'].')' : null).($array ? '[]' : '');
    }
}
