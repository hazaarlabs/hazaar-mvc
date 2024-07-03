<?php

declare(strict_types=1);

namespace Hazaar\DBI2;

use Hazaar\DBI2\Interfaces\QueryBuilder;
use Hazaar\DBI2\Interfaces\Result;

class Table
{
    private Adapter $adapter;
    private QueryBuilder $queryBuilder;
    private string $table;
    private ?Result $result = null;

    public function __construct(Adapter $adapter, string $table, ?string $alias = null)
    {
        $this->table = $table;
        if (null !== $alias) {
            $this->table .= ' '.$alias;
        }
        $this->adapter = $adapter;
        $this->queryBuilder = $adapter->getQueryBuilder();
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
            return $this->adapter->tableExists($this->table);
        }
        $sql = $this->queryBuilder->exists($this->table, $criteria);

        return $this->adapter->query($sql)->fetchColumn(0);
    }

    /**
     * @param array<string>|string $columns
     */
    public function select(array|string $columns = '*'): self
    {
        $this->queryBuilder->select($columns)->from($this->table);

        return $this;
    }

    public function distinct(string ...$columns): self
    {
        $this->queryBuilder->distinct(...$columns);

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
     * @param array<string,int>|string $columns
     */
    public function order(array|string $columns, int $direction = SORT_ASC): self
    {
        $this->queryBuilder->order($columns, $direction);

        return $this;
    }

    /**
     * @param array<mixed>|string $columns
     */
    public function group(array|string $columns): self
    {
        $this->queryBuilder->group($columns);

        return $this;
    }

    /**
     * @param array<mixed>|string $having
     */
    public function having(array|string $having): self
    {
        $this->queryBuilder->having($having);

        return $this;
    }

    /**
     * Join a table to the current query using the provided join criteria.
     *
     * @param array<mixed>|string $on    The join criteria.  This is mostly just a standard query selection criteria.
     * @param string              $alias an alias to use for the joined table
     * @param string              $type  the join type such as INNER, OUTER, LEFT, RIGHT, etc
     */
    public function join(string $table, array|string $on, ?string $alias = null, string $type = 'INNER'): self
    {
        $this->queryBuilder->join($table, $on, $alias, $type);

        return $this;
    }

    /**
     * Join a table to the current query using the provided join criteria.
     *
     * @param array<mixed>|string $on    The join criteria.  This is mostly just a standard query selection criteria.
     * @param string              $alias an alias to use for the joined table
     */
    public function leftJoin(string $table, array|string $on, ?string $alias = null): self
    {
        $this->queryBuilder->join($table, $on, $alias, 'LEFT');

        return $this;
    }

    /**
     * Join a table to the current query using the provided join criteria.
     *
     * @param array<mixed>|string $on    The join criteria.  This is mostly just a standard query selection criteria.
     * @param string              $alias an alias to use for the joined table
     */
    public function rightJoin(string $table, array|string $on, ?string $alias = null): self
    {
        $this->queryBuilder->join($table, $on, $alias, 'RIGHT');

        return $this;
    }

    /**
     * Join a table to the current query using the provided join criteria.
     *
     * @param array<mixed>|string $on    The join criteria.  This is mostly just a standard query selection criteria.
     * @param string              $alias an alias to use for the joined table
     */
    public function innerJoin(string $table, array|string $on, ?string $alias = null): self
    {
        $this->queryBuilder->join($table, $on, $alias, 'INNER');

        return $this;
    }

    /**
     * Join a table to the current query using the provided join criteria.
     *
     * @param array<mixed>|string $on    The join criteria.  This is mostly just a standard query selection criteria.
     * @param string              $alias an alias to use for the joined table
     */
    public function outerJoin(string $table, array|string $on, ?string $alias = null): self
    {
        $this->queryBuilder->join($table, $on, $alias, 'OUTER');

        return $this;
    }

    /**
     * Join a table to the current query using the provided join criteria.
     *
     * @param array<mixed>|string $on    The join criteria.  This is mostly just a standard query selection criteria.
     * @param string              $alias an alias to use for the joined table
     */
    public function fullJoin(string $table, array|string $on, ?string $alias = null): self
    {
        $this->queryBuilder->join($table, $on, $alias, 'FULL');

        return $this;
    }

    /**
     * Join a table to the current query using the provided join criteria.
     *
     * @param array<mixed>|string $on    The join criteria.  This is mostly just a standard query selection criteria.
     * @param string              $alias an alias to use for the joined table
     */
    public function crossJoin(string $table, array|string $on, ?string $alias = null): self
    {
        $this->queryBuilder->join($table, $on, $alias, 'CROSS');

        return $this;
    }

    /**
     * @param array<mixed>|Table $values
     */
    public function insert(array|Table $values, mixed $returning = null): mixed
    {
        $result = $this->adapter->query($this->queryBuilder->insert($this->table, $values, $returning));
        if (!$result) {
            return false;
        }

        return null === $returning
            ? $result->rowCount()
            : ($result->columnCount() > 1 ? $result->fetch() : $result->fetchColumn(0));
    }

    /**
     * @param array<mixed>|string $where
     */
    public function update(mixed $values, array|string $where = [], mixed $returning = null): mixed
    {
        $result = $this->adapter->query($this->queryBuilder->update($this->table, $values, $where, [], $returning));

        return null === $returning
            ? $result->rowCount()
            : ($result->columnCount() > 1 ? $result->fetch() : $result->fetchColumn(0));
    }

    /**
     * @param array<mixed>|string $where
     */
    public function delete(array|string $where): false|int
    {
        return $this->adapter->query($this->queryBuilder->delete($this->table, $where));
    }

    public function deleteAll(): false|int
    {
        return $this->adapter->exec($this->queryBuilder->delete($this->table, []));
    }

    public function truncate(bool $cascade = false): bool
    {
        return false !== $this->adapter->exec($this->queryBuilder->truncate($this->table, $cascade));
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

        return $this->adapter->query($this->queryBuilder->toString());
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
        $result = $this->adapter->query($this->queryBuilder->limit(1)->toString());
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
        $result = $this->adapter->query($this->queryBuilder->limit(1)->toString());
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
            $this->result = $this->adapter->query($this->queryBuilder->toString());
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
        $result = $this->adapter->query($this->queryBuilder->toString());
        if ($result instanceof Result) {
            return $result->fetchAll();
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    public function fetchAllColumn(
        string $columnName,
        mixed $fetchArgument = null,
        bool $clobberDupNamedCols = false
    ): array {
        $result = $this->adapter->query($this->queryBuilder->select($columnName)->from($this->table)->toString());
        $data = [];
        while ($row = $result->fetch()) {
            $data[] = $row[$columnName];
        }

        return $data;
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
        $result = $this->adapter->query($this->queryBuilder->count());
        if ($result) {
            return $result->fetchColumn(0);
        }

        return 0;
    }

    /**
     * @return array<array{name:string,data_type:string,not_null:bool,default:?mixed,length:?int,sequence:?string}>|false
     */
    public function describe(): array|false
    {
        return $this->adapter->describeTable($this->table);
    }
}
