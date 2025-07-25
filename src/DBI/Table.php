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

    public function select(mixed $columns = '*'): self
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

    public function group(string ...$columns): self
    {
        $this->queryBuilder->group(...$columns);

        return $this;
    }

    /**
     * @param array<mixed> $having
     */
    public function having(array $having): self
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
     * Prepares a SELECT statement with the specified column names and criteria names.
     *
     * @param array<string> $columns       the names of the columns to be selected
     * @param array<string> $criteriaNames the names of the columns to be used as criteria for the selection
     *
     * @return Statement the prepared SELECT statement
     */
    public function prepareSelect(array $columns, array $criteriaNames): Statement
    {
        $criteria = array_combine($criteriaNames, array_fill(0, count($criteriaNames), null));

        return $this->adapter->prepareQuery($this->queryBuilder->select($columns)->where($criteria));
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
        $queryBuilder = $this->queryBuilder->insert($values)
            ->returning($returning)
            ->onConflict($conflictTarget, $conflictUpdate)
        ;
        $statement = $this->adapter->prepareQuery($queryBuilder);
        $result = $statement->execute();
        if (!$result) {
            return false;
        }
        if (null === $returning) {
            return $statement->rowCount();
        }

        return ($statement->columnCount() > 1) ? $statement->fetch() : $statement->fetchColumn(0);
    }

    /**
     * Prepares an SQL INSERT statement with the given column names.
     *
     * This method takes an array of column names and creates an associative array
     * where each column name is a key and the value is set to null. It then uses
     * the query builder to generate an INSERT query with these values and prepares
     * the query using the adapter.
     *
     * @param array<string> $columnNames an array of column names to be included in the INSERT statement
     *
     * @return Statement the prepared SQL INSERT statement
     */
    public function prepareInsert(array $columnNames): Statement
    {
        $values = array_combine($columnNames, array_fill(0, count($columnNames), null));

        return $this->adapter->prepareQuery($this->queryBuilder->insert($values));
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
        $queryBuilder = $this->queryBuilder->insert($values)
            ->returning(array_diff($model->keys(), array_keys($values)))
            ->onConflict($conflictTarget, $conflictUpdate)
        ;
        $statement = $this->adapter->prepareQuery($queryBuilder);
        $result = $statement->execute();
        if (!$result) {
            return false;
        }
        $model->extend($statement->fetch(\PDO::FETCH_ASSOC));

        return $model;
    }

    /**
     * Updates records in the database table.
     *
     * @param mixed               $values    The values to update in the table. This can be an associative array of column-value pairs.
     * @param array<mixed>|string $where     The conditions to identify the records to update. This can be an associative array of column-value pairs or a string condition.
     * @param mixed               $returning Optional. Specifies the columns to return after the update. This can be a string or an array of column names.
     *
     * @return mixed Returns the number of affected rows if $returning is null. If $returning is specified, returns the fetched result. Returns false if the update fails.
     */
    public function update(mixed $values, array|string $where = [], mixed $returning = null): mixed
    {
        $queryBuilder = $this->queryBuilder->update($values)
            ->where($where)
            ->returning($returning)
        ;
        $statement = $this->adapter->prepareQuery($queryBuilder);
        $result = $statement->execute();
        if (!$result) {
            return false;
        }

        return null === $returning
            ? $statement->rowCount()
            : ($statement->columnCount() > 1 ? $statement->fetch() : $statement->fetchColumn(0));
    }

    /**
     * Prepares an update statement with the specified column names and criteria names.
     *
     * @param array<string> $columnNames   the names of the columns to be updated
     * @param array<string> $criteriaNames the names of the columns to be used as criteria for the update
     *
     * @return Statement the prepared update statement
     *
     * @throws \InvalidArgumentException if column names and criteria names overlap
     */
    public function prepareUpdate(array $columnNames, array $criteriaNames = []): Statement
    {
        $duplicateNames = array_intersect($columnNames, $criteriaNames);
        if (count($duplicateNames) > 0) {
            throw new \InvalidArgumentException('Column names and criteria names must not overlap');
        }
        $values = array_combine($columnNames, array_fill(0, count($columnNames), null));
        $criteria = array_combine($criteriaNames, array_fill(0, count($criteriaNames), null));

        return $this->adapter->prepareQuery($this->queryBuilder->where($criteria)->update($values));
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
        $queryBuilder = $this->queryBuilder->update($values)
            ->where($criteria)
            ->returning(array_diff($model->keys(), array_keys($values)))
        ;
        $statement = $this->adapter->prepareQuery($queryBuilder);
        $result = $statement->execute();
        if (!$result) {
            return false;
        }
        $model->extend($statement->fetch());

        return $model;
    }

    /**
     * @param array<mixed>|string $where
     */
    public function delete(array|string $where): false|int
    {
        $queryBuilder = $this->queryBuilder->delete()
            ->where($where)
        ;
        $statement = $this->adapter->prepareQuery($queryBuilder);
        $result = $statement->execute();

        if (false === $result) {
            return false;
        }

        return $statement->rowCount();
    }

    /**
     * Prepares a DELETE statement with the specified criteria names.
     *
     * @param array<string> $criteriaNames the names of the columns to be used as criteria for the deletion
     *
     * @return Statement the prepared DELETE statement
     */
    public function prepareDelete(array $criteriaNames): Statement
    {
        $criteria = array_combine($criteriaNames, array_fill(0, count($criteriaNames), null));

        return $this->adapter->prepareQuery($this->queryBuilder->where($criteria)->delete());
    }

    public function deleteAll(): false|int
    {
        $queryBuilder = $this->queryBuilder->delete();

        return $this->adapter->exec($queryBuilder->toString());
    }

    public function truncate(bool $cascade = false): bool
    {
        $queryBuilder = $this->queryBuilder->truncate($cascade);

        return false !== $this->adapter->exec($queryBuilder->toString());
    }

    /**
     * @param null|array<mixed>|string $columns
     */
    public function find(mixed $where = null, mixed $columns = null): false|Result
    {
        $this->select($columns);
        if (null !== $where) {
            $this->where($where);
        }
        $statement = $this->adapter->prepareQuery($this->queryBuilder);
        if ($statement->execute()) {
            return new \Hazaar\DBI\Result\PDO($statement);
        }

        return false;
    }

    /**
     * @param array<mixed>|string $where
     */
    public function where(array|string $where): self
    {
        $this->queryBuilder->where($where);

        return $this;
    }

    /**
     * @param array<mixed>|string       $where
     * @param null|array<string>|string $columns
     *
     * @return array<mixed>|false
     */
    public function findOne(array|string $where, mixed $columns = null): array|false
    {
        $this->queryBuilder->select($columns)->where($where);
        $statement = $this->adapter->prepareQuery($this->queryBuilder->limit(1));
        if ($statement->execute()) {
            return $statement->fetch(\PDO::FETCH_ASSOC);
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
        $this->queryBuilder->select($columns)->where($where);
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
