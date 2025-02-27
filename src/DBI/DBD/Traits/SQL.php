<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD\Traits;

use Hazaar\DBI\Interface\QueryBuilder;
use Hazaar\DBI\QueryBuilder\SQL as SQLBuilder;

trait SQL
{
    private ?string $schemaName;

    public function initQueryBuilder(?string $schemaName = null): void
    {
        $this->schemaName = $schemaName;
    }

    public function getQueryBuilder(): QueryBuilder
    {
        if (!isset($this->schemaName)) {
            throw new \Exception('Query builder not initialized');
        }
        $queryBuilder = new SQLBuilder($this->schemaName);
        if (isset(self::$reservedWords)) {
            $queryBuilder->setReservedWords(self::$reservedWords);
        }

        return $queryBuilder;
    }

    public function createDatabase(string $name): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        $sql = 'CREATE DATABASE '.$queryBuilder->quoteSpecial($name).';';
        $result = $this->query($sql);

        return false !== $result;
    }

    /**
     * @param array<string>|string $privilege
     */
    public function grant(array|string $privilege, string $to, string $object): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        if (is_array($privilege)) {
            $privilege = implode(', ', $privilege);
        }
        $sql = 'GRANT '.$privilege
            .' ON '.$queryBuilder->schemaName($object)
            .' TO '.$queryBuilder->quoteSpecial($to);

        return false !== $this->exec($sql);
    }

    /**
     * @param array<string>|string $privilege
     */
    public function revoke(array|string $privilege, string $object, string $from): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        if (is_array($privilege)) {
            $privilege = implode(', ', $privilege);
        }
        $sql = 'REVOKE '.$privilege
            .' ON '.$queryBuilder->schemaName($object)
            .' FROM '.$queryBuilder->quoteSpecial($from);

        return false !== $this->exec($sql);
    }

    protected function fixValue(mixed $value): mixed
    {
        return $value;
    }

    protected function type(?string $dataType, ?int $length = null): string
    {
        if (!$dataType) {
            return 'character varying';
        }
        if ($array = ('[]' === substr($dataType, -2))) {
            $dataType = substr($dataType, 0, -2);
        }

        return $dataType.($length ? '('.$length.')' : null).($array ? '[]' : '');
    }
}
