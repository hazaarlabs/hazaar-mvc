<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD\Traits;

use Hazaar\DBI\Interface\QueryBuilder;
use Hazaar\DBI\QueryBuilder\SQL as SQLBuilder;

trait SQL
{
    public function getQueryBuilder(): QueryBuilder
    {
        return new SQLBuilder($this->config['schema'] ?? 'public');
    }

    public function createDatabase(string $name): bool
    {
        $sql = 'CREATE DATABASE '.$this->queryBuilder->quoteSpecial($name).';';
        $result = $this->query($sql);

        return true;
    }

    /**
     * @param array<string>|string $privilege
     */
    public function grant(array|string $privilege, string $to, string $object): bool
    {
        if (is_array($privilege)) {
            $privilege = implode(', ', $privilege);
        }
        $sql = 'GRANT '.$privilege
            .' ON '.$this->queryBuilder->schemaName($object)
            .' TO '.$this->queryBuilder->quoteSpecial($to);

        return false !== $this->exec($sql);
    }

    /**
     * @param array<string>|string $privilege
     */
    public function revoke(array|string $privilege, string $object, string $from): bool
    {
        if (is_array($privilege)) {
            $privilege = implode(', ', $privilege);
        }
        $sql = 'REVOKE '.$privilege
            .' ON '.$this->queryBuilder->schemaName($object)
            .' FROM '.$this->queryBuilder->quoteSpecial($from);

        return false !== $this->exec($sql);
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
        if (!($type = ake($info, 'type'))) {
            return 'character varying';
        }
        if ($array = ('[]' === substr($type, -2))) {
            $type = substr($type, 0, -2);
        }

        return $type.(ake($info, 'length') ? '('.$info['length'].')' : null).($array ? '[]' : '');
    }
}
