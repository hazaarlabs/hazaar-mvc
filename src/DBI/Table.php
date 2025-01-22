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
     */
    public function insert(
        mixed $values,
        null|array|string $returning = null,
        null|array|string $conflictTarget = null,
        mixed $conflictUpdate = null,
        ?Table $table = null
    ): mixed {
        $sqlString = $this->queryBuilder->insert($this->table, $values, $returning, $conflictTarget, $conflictUpdate, $table);
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
        $sqlString = $this->queryBuilder->insert($this->table, $values, $returning, $conflictTarget, $conflictUpdate);
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
        $result = $this->adapter->query($this->queryBuilder->update($this->table, $values, $where, [], $returning));

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
        $result = $this->adapter->query($this->queryBuilder->update($this->table, $values, $criteria, [], $returning));
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
        $result = $this->adapter->query($this->queryBuilder->delete($this->table, $where));
        if (false === $result) {
            return false;
        }

        return $result->rowCount();
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
     * @param null|array<mixed>|string $columns
     */
    public function find(mixed $where = null, null|array|string $columns = null): self
    {
        if (null !== $columns) {
            $this->select($columns);
        }
        if (null !== $where) {
            $this->where($where);
        }

        return $this;
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
        $this->result = $this->adapter->query($this->queryBuilder->limit(1)->toString());
        if ($this->result) {
            return $this->result->fetch();
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
        $this->result = $this->adapter->query($this->queryBuilder->limit(1)->toString());
        if ($this->result) {
            return $this->result->row();
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
        $this->result = $this->adapter->query($this->queryBuilder->toString());
        if (false !== $this->result) {
            return $this->result->fetchAll();
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
        $this->result = $this->adapter->query($this->queryBuilder->select($columnName)->from($this->table)->toString());
        $data = [];
        while ($row = $this->result->fetch()) {
            $data[] = $row[$columnName];
        }

        return $data;
    }

    /**
     * Fetches a model instance of the specified class.
     *
     * @param string $modelClass the fully qualified class name of the model to fetch
     *
     * @return false|Model returns an instance of the specified model class populated with data from the database,
     *                     or false if no data is found
     *
     * @throws \Exception if the specified model class does not exist or is not a subclass of Model
     */
    public function fetchModel(
        string $modelClass
    ): false|Model {
        if (!class_exists($modelClass)) {
            throw new \Exception('Model class does not exist: '.$modelClass);
        }
        if (!is_subclass_of($modelClass, Model::class)) {
            throw new \Exception('Model class must be a subclass of '.Model::class);
        }
        $rowData = $this->fetch();
        if (false === $rowData) {
            return false;
        }

        return new $modelClass($rowData);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $this->result = $this->adapter->query($this->queryBuilder->toString());
        $rows = [];
        while ($row = $this->result->row()) {
            $rows[] = $row->toArray();
        }

        return $rows;
    }

    public function row(): false|Row
    {
        if (null === $this->result) {
            $this->result = $this->adapter->query($this->queryBuilder->toString());
        }
        if (false !== $this->result) {
            return $this->result->row();
        }

        return false;
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

    public function result(): Result
    {
        if (null === $this->result) {
            $this->result = $this->adapter->query($this->queryBuilder->toString());
        }

        return $this->result;
    }

    /**
     * @return array<int,string>
     */
    public function errorInfo(): array
    {
        return $this->adapter->errorInfo();
    }

    public function reset(): bool
    {
        $this->queryBuilder->reset();
        $this->result = null;

        return true;
    }

    public function execute(): false|int
    {
        return $this->result = $this->adapter->query($this->queryBuilder->toString());
    }

    public function create(mixed $columns): bool
    {
        return $this->adapter->createTable($this->table, $columns);
    }

    public function drop(): bool
    {
        return $this->adapter->dropTable($this->table);
    }
}
