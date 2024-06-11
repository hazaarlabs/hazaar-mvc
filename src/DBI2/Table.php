<?php

declare(strict_types=1);

namespace Hazaar\DBI2;

use Hazaar\DBI2\DBD\Interfaces\Driver;
use Hazaar\DBI2\Interfaces\Result;

class Table
{
    private Driver $adapter;
    private string $table;
    private ?Result $result = null;

    public function __construct(Driver $adapter, string $table)
    {
        if (!in_array('Hazaar\DBI2\DBD\Traits\SQL', class_uses($adapter))) {
            throw new \Exception(get_class($adapter).' does not support SQL queries!');
        }
        $this->table = $table;
        $this->adapter = $adapter;
        $this->adapter->select()->table($this->table);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->adapter->toString();
    }

    /**
     * @param array<string>|string $columns
     */
    public function select(array|string $columns = '*'): self
    {
        $this->adapter->select($columns);

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->adapter->limit($limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->adapter->offset($offset);

        return $this;
    }

    /**
     * @param array<string> $values
     */
    public function insert(array $values, ?string $returning = null): mixed
    {
        // dump($this->adapter->insert($values, $returning)->toString());
        $result = $this->adapter->insert($values, $returning)->go();
        if (null !== $returning) {
            if ('*' === $returning) {
                return $result->fetch();
            }

            return $result->fetchColumn(0);
        }

        return $result->rowCount();
    }

    /**
     * @param array<string>|string      $where
     * @param null|array<string>|string $columns
     */
    public function find(null|array|string $where = null, null|array|string $columns = null): mixed
    {
        if (null !== $where) {
            $this->adapter->where($where);
        }
        if (null !== $columns) {
            $this->select($columns);
        }

        return $this->adapter->go();
    }

    /**
     * @param array<string>|string      $where
     * @param null|array<string>|string $columns
     *
     * @return array<mixed>|false
     */
    public function findOne(array|string $where, null|array|string $columns = null): array|false
    {
        $this->adapter->where($where);
        if (null !== $columns) {
            $this->select($columns);
        }

        return $this->adapter->limit(1)->go()->fetch();
    }

    /**
     * @return array<mixed>|false
     */
    public function fetch(): array|false
    {
        if (null === $this->result) {
            $this->result = $this->adapter->go();
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
        $result = $this->adapter->go();
        if ($result instanceof Result) {
            return $result->fetchAll();
        }

        return false;
    }
}
