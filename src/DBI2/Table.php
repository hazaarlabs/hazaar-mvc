<?php

declare(strict_types=1);

namespace Hazaar\DBI2;

use Hazaar\DBI2\DBD\Interfaces\Driver;
use Hazaar\DBI2\Interfaces\QueryBuilder;
use Hazaar\DBI2\Interfaces\Result;

class Table
{
    private Driver $driver;
    private QueryBuilder $queryBuilder;
    private string $table;
    private ?Result $result = null;

    public function __construct(Driver $driver, string $table)
    {
        $this->table = $table;
        $this->driver = $driver;
        $this->queryBuilder = $driver->getQueryBuilder();
        $this->queryBuilder->from($this->table);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->queryBuilder->toString();
    }

    public function exists(mixed $criteria = null): bool
    {
        if (null === $criteria) {
            $criteria = [
                'table_name' => $this->table,
                'table_schema' => $this->queryBuilder->getSchemaName(),
            ];
            $tableName = 'information_schema.tables';
        } else {
            $tableName = $this->table;
        }
        $sql = $this->queryBuilder->exists($tableName, $criteria);

        return $this->driver->query($sql)->fetchColumn(0);
    }

    /**
     * @param array<string>|string $columns
     */
    public function select(array|string $columns = '*'): self
    {
        $this->queryBuilder->select($columns);

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->queryBuilder->limit($limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->queryBuilder->offset($offset);

        return $this;
    }

    /**
     * @param array<mixed> $values
     */
    public function insert(array $values, mixed $returning = null): mixed
    {
        $result = $this->driver->query($this->queryBuilder->insert($this->table, $values, $returning));
        if (null !== $returning) {
            if ('*' === $returning) {
                return $result->fetch();
            }

            return $result->fetchColumn(0);
        }

        return $result->rowCount();
    }

    /**
     * @param array<mixed>|string $where
     */
    public function update(mixed $values, array|string $where = [], mixed $returning = null): mixed
    {
        $result = $this->driver->query($this->queryBuilder->update($this->table, $values, $where, [], $returning));
        if (null !== $returning) {
            if ('*' === $returning) {
                return $result->fetch();
            }

            return $result->fetchColumn(0);
        }

        return $result->rowCount();
    }

    /**
     * @param array<mixed>|string       $where
     * @param null|array<string>|string $columns
     */
    public function find(null|array|string $where = null, null|array|string $columns = null): mixed
    {
        if (null !== $where) {
            $this->queryBuilder->where($where);
        }
        if (null !== $columns) {
            $this->select($columns);
        }

        return $this->driver->query($this->queryBuilder->toString());
    }

    /**
     * @param array<mixed>|string       $where
     * @param null|array<string>|string $columns
     *
     * @return array<mixed>|false
     */
    public function findOne(array|string $where, null|array|string $columns = null): array|false
    {
        $this->queryBuilder->where($where);
        if (null !== $columns) {
            $this->select($columns);
        }
        $result = $this->driver->query($this->queryBuilder->limit(1)->toString());
        if ($result) {
            return $result->fetch();
        }

        return false;
    }

    /**
     * @param array<mixed>|string       $where
     * @param null|array<string>|string $columns
     */
    public function findOneRow(array|string $where, null|array|string $columns = null): false|Row
    {
        $this->queryBuilder->where($where);
        if (null !== $columns) {
            $this->select($columns);
        }
        $result = $this->driver->query($this->queryBuilder->limit(1)->toString());
        if ($result) {
            return $result->row();
        }

        return false;
    }

    /**
     * @return array<mixed>|false
     */
    public function fetch(): array|false
    {
        if (null === $this->result) {
            $this->result = $this->driver->query($this->queryBuilder->select()->toString());
        }
        if ($this->result instanceof Result) {
            return $this->result->fetch();
        }

        return false;
    }

    /**
     * @return array<mixed>|false
     */
    public function fetchAll(): array|false
    {
        $result = $this->driver->query($this->queryBuilder->select()->toString());
        if ($result instanceof Result) {
            return $result->fetchAll();
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $rows = [];
        $result = $this->find()->rows();
        foreach ($result as &$row) {
            $rows[] = $row->toArray();
        }

        return $rows;
    }

    public function count(): int
    {
        $result = $this->driver->query($this->queryBuilder->count());
        if ($result) {
            return $result->fetchColumn(0);
        }

        return 0;
    }
}
