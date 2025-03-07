<?php

declare(strict_types=1);

namespace Hazaar\DBI;

use Hazaar\DBI\Interface\QueryBuilder;
use Hazaar\DBI\Interface\Result;
use Hazaar\Model;

class Table
{
    private Adapter $adapter;
    private QueryBuilder $queryBuilder;
    private string $name;

    public function __construct(Adapter $adapter, string $name, ?string $alias = null)
    {
        $this->name = $name;
        if (null !== $alias) {
            $this->name .= ' '.$alias;
        }
        $this->adapter = $adapter;
        $this->queryBuilder = $adapter->getQueryBuilder();
        $this->queryBuilder->from($this->name);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->queryBuilder->toString();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function exists(mixed $criteria = null): bool
    {
        if (null === $criteria) {
            return $this->adapter->tableExists($this->name);
        }
        $sql = $this->queryBuilder->exists($this->name, $criteria);

        return $this->adapter->query($sql)->fetchColumn(0);
    }

    /**
     * @param array<mixed>|string $columns
     */
    public function select(array|string $columns = '*'): self
    {
        $this->queryBuilder->select($columns);

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
     * @param null|array<mixed>|string $returning
     * @param array<mixed>             $conflictTarget
     * @param array<string>|bool       $conflictUpdate
     */
    public function insert(
        mixed $values,
        null|array|string $returning = null,
        null|array|string $conflictTarget = null,
        null|array|bool $conflictUpdate = null,
        ?Table $table = null
    ): mixed {
        $sqlString = $this->queryBuilder->insert($this->name, $values, $returning, $conflictTarget, $conflictUpdate, $table);
        $result = $this->adapter->query($sqlString);
        if (!$result) {
            return false;
        }
        if (null === $returning) {
            return $result->rowCount();
        }

        return ($result->columnCount() > 1) ? $result->fetch() : $result->fetchColumn(0);
    }

    /**
     * @param array<mixed> $conflictTarget
     */
    public function insertModel(
        Model $model,
        null|array|string $conflictTarget = null,
        mixed $conflictUpdate = null,
    ): false|Model {
        $values = $model->toArray();
        // For efficiency, we only return the values that were not set by the model
        $returning = array_diff($model->keys(), array_keys($values));
        $sqlString = $this->queryBuilder->insert($this->name, $values, $returning, $conflictTarget, $conflictUpdate);
        $result = $this->adapter->query($sqlString);
        if (!$result) {
            return false;
        }
        $model->extend($result->fetch());

        return $model;
    }

    /**
     * @param array<mixed>|string $where
     */
    public function update(mixed $values, array|string $where = [], mixed $returning = null): mixed
    {
        $result = $this->adapter->query($this->queryBuilder->update($this->name, $values, $where, [], $returning));

        return null === $returning
            ? $result->rowCount()
            : ($result->columnCount() > 1 ? $result->fetch() : $result->fetchColumn(0));
    }

    /**
     * Updates the given model in the database based on the specified criteria.
     *
     * @param Model                $model the model instance to be updated
     * @param array<string>|string $where The criteria for selecting the record(s) to be updated. Can be an array of keys or a single key.
     *
     * @return false|Model returns the updated model instance on success, or false on failure
     */
    public function updateModel(Model $model, array|string $where = []): false|Model
    {
        if (!is_array($where)) {
            $where = (array) $where;
        }
        $criteria = [];
        foreach ($where as $key) {
            if (!$model->has($key)) {
                continue;
            }
            $criteria[$key] = $model->get($key);
        }
        $values = $model->toArray();
        $returning = array_diff($model->keys(), array_keys($values));
        $result = $this->adapter->query($this->queryBuilder->update($this->name, $values, $criteria, [], $returning));
        if (!$result) {
            return false;
        }
        $model->extend($result->fetch());

        return $model;
    }

    /**
     * @param array<mixed>|string $where
     */
    public function delete(array|string $where): false|int
    {
        $result = $this->adapter->query($this->queryBuilder->delete($this->name, $where));
        if (false === $result) {
            return false;
        }

        return $result->rowCount();
    }

    public function deleteAll(): false|int
    {
        return $this->adapter->exec($this->queryBuilder->delete($this->name, []));
    }

    public function truncate(bool $cascade = false): bool
    {
        return false !== $this->adapter->exec($this->queryBuilder->truncate($this->name, $cascade));
    }

    /**
     * @param null|array<mixed>|string $columns
     */
    public function find(mixed $where = null, null|array|string $columns = null): false|Result
    {
        if (null !== $columns) {
            $this->select($columns);
        }
        if (null !== $where) {
            $this->where($where);
        }
        $result = $this->adapter->query($this->queryBuilder->toString());
        if ($result) {
            return $result;
        }

        return false;
    }

    public function where(mixed $where = null): self
    {
        if (null !== $where) {
            $this->queryBuilder->where($where);
        }

        return $this;
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
     * Finds a single model instance based on the specified conditions.
     *
     * @param string              $model the fully qualified class name of the model to instantiate
     * @param array<mixed>|string $where the conditions to use for finding the record
     *
     * @return false|Model returns an instance of the model if found, or false if no record matches the conditions
     */
    public function findOneModel(string $model, array|string $where): false|Model
    {
        $rowData = $this->findOne($where);
        if (false === $rowData) {
            return false;
        }

        return new $model($rowData);
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
    public function fetchAll(?string $keyColumn = null): array|false
    {
        $result = $this->adapter->query($this->queryBuilder->toString());
        if (false === $result) {
            return false;
        }
        if (null === $keyColumn) {
            return $result->fetchAll();
        }
        $rows = [];
        while ($row = $result->fetch()) {
            $rows[$row[$keyColumn]] = $row;
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    public function fetchAllColumn(
        string $columnName,
        mixed $fetchArgument = null,
        bool $clobberDupNamedCols = false
    ): array|false {
        $result = $this->adapter->query($this->queryBuilder->select($columnName)->from($this->name)->toString());
        if (!$result) {
            return false;
        }
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
        $result = $this->adapter->query($this->queryBuilder->toString());
        if (false === $result) {
            return $rows;
        }
        while ($row = $result->row()) {
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
        return $this->adapter->describeTable($this->name);
    }

    /**
     * @return array<int,string>
     */
    public function errorInfo(): array
    {
        return $this->adapter->errorInfo();
    }

    public function execute(): false|int
    {
        $result = $this->adapter->query($this->queryBuilder->toString());
        if (!$result) {
            return false;
        }

        return $result->fetchColumn(0);
    }

    public function create(mixed $columns): bool
    {
        return $this->adapter->createTable($this->name, $columns);
    }

    public function drop(): bool
    {
        return $this->adapter->dropTable($this->name);
    }

    /**
     * @return array{name: string, table: string, column: string, type: string}|false
     */
    public function getPrimaryKey(): array|false
    {
        $constraints = $this->adapter->listConstraints($this->name, 'PRIMARY KEY');
        if (0 === count($constraints)) {
            return false;
        }

        return array_shift($constraints);
    }

    public function reset(): self
    {
        $this->queryBuilder->reset();

        return $this;
    }
}
