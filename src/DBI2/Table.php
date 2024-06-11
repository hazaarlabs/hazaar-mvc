<?php

declare(strict_types=1);

namespace Hazaar\DBI2;

use Hazaar\DBI2\DBD\Interfaces\Driver;

class Table
{
    private Driver $queryBuilder;
    private string $table;
    private ?Result $result = null;

    public function __construct(Driver $queryBuilder, string $table)
    {
        if (!in_array('Hazaar\DBI2\DBD\Traits\SQL', class_uses($queryBuilder))) {
            throw new \Exception(get_class($queryBuilder).' does not support SQL queries!');
        }
        $this->table = $table;
        $this->queryBuilder = $queryBuilder;
        $this->queryBuilder->select()->from($this->table);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->queryBuilder->toString();
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
     * @param array<string>|string      $where
     * @param null|array<string>|string $columns
     */
    public function find(array|string $where, null|array|string $columns = null): mixed
    {
        $this->queryBuilder->where($where);
        if (null !== $columns) {
            $this->select($columns);
        }

        return $this->queryBuilder->go();
    }

    /**
     * @param array<string>|string      $where
     * @param null|array<string>|string $columns
     *
     * @return array<mixed>
     */
    public function findOne(array|string $where, null|array|string $columns = null): array
    {
        $this->queryBuilder->where($where);
        if (null !== $columns) {
            $this->select($columns);
        }

        return $this->queryBuilder->limit(1)->go()->fetch();
    }

    /**
     * @return array<mixed>|false
     */
    public function fetch(): array|false
    {
        if (null === $this->result) {
            $this->result = $this->queryBuilder->go();
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
        $result = $this->queryBuilder->go();
        if ($result instanceof Result) {
            return $result->fetchAll();
        }

        return false;
    }
}
