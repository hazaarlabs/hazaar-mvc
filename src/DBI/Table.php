<?php

declare(strict_types=1);

/**
 * @file        Hazaar/DBI/Table.php
 *
 * @author      Jamie Carl <jamie@hazaar.io.com>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\DBI;

/**
 * @brief Relational Database Interface - Table Class
 *
 * @detail The Table class is used to access table data via an abstracted interface. That means that now SQL is
 * used to access table data and queries are generated automatically using access methods. The generation
 * of SQL is then handled by the database driver so that database specific SQL can be used when required.
 * This allows a common interface for accessing data that is compatible across all of the database drivers.
 *
 * h2. Example Usage
 *
 * ```php
 * $db = new Hazaar\DBI();
 * $result = $db->users->find(array('uname' => 'myusername'))->join('images', array('image' => array('$ref' => 'images.id')));
 * while($row = $result->fetch()){
 * //Do things with $row here
 * }
 * ```
 *
 * @implements \Iterator<Row>
 */
class Table implements \Iterator
{
    protected Adapter $adapter;
    protected ?string $tableName;
    protected ?string $alias;

    /**
     * @var array<Table>
     */
    protected array $tables = [];

    /**
     * @var array<string>
     */
    protected array $criteria = [];

    /**
     * @var array<string>
     */
    protected array $fields = [];

    /**
     * @var array<string>
     */
    protected array $group = [];

    /**
     * @var array<string>
     */
    protected array $having = [];

    /**
     * @var array<array<string>>
     */
    protected array $window = [];

    /**
     * @var array<array<string>>
     */
    protected array $joins = [];

    /**
     * @var array<string>
     */
    protected array $combine = [];

    /**
     * @var array<string, int>
     */
    protected array $order = [];

    /**
     * @var array<string>
     */
    protected array $fetch = [];

    /**
     * @var array<string>|bool
     */
    protected array|bool $distinct = false;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?Result $result = null;

    public function __construct(Adapter $adapter, ?string $tableName = null, ?string $alias = null)
    {
        $this->adapter = $adapter;
        $this->tableName = $tableName;
        $this->alias = $alias;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Search for records on a table with the provided search criteria.
     *
     * @param mixed $criteria the search criteria to find records for
     * @param mixed $fields   a field definition
     */
    public function find(mixed $criteria = [], mixed $fields = []): Table
    {
        if (!is_array($criteria)) {
            $criteria = [$criteria];
        }
        $this->criteria = $criteria;
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        if (is_array($fields) && count($fields) > 0) {
            $this->fields = $fields;
        }

        return $this;
    }

    /**
     * Find a single row using the provided criteria, fields and order and return is as an array.
     *
     * @param mixed                    $criteria the search criteria
     * @param mixed                    $fields   a field definition array
     * @param array<string,int>|string $order    A valid order definition
     *
     * @return null|array<mixed>|false
     */
    public function findOne(mixed $criteria = [], mixed $fields = [], null|array|string $order = null): null|array|false
    {
        $table = $this->find($criteria, $fields);
        if ($order) {
            $table->sort($order);
        }

        return $table->fetch();
    }

    /**
     * Find a single row using the provided criteria, fields and order and return is as an array.
     *
     * @param mixed                    $criteria the search criteria
     * @param mixed                    $fields   a field definition array
     * @param array<string,int>|string $order    A valid order definition
     */
    public function findOneRow(mixed $criteria = [], mixed $fields = [], $order = null): null|Row
    {
        $table = $this->find($criteria, $fields);
        if ($order) {
            $table->sort($order);
        }

        return $table->row();
    }

    /**
     * Check if rows exist in the database.
     *
     * @param mixed $criteria the search criteria to check for existing rows
     */
    public function exists(mixed $criteria = null): bool
    {
        if (null === $criteria && !$this->criteria) {
            return $this->adapter->driver->tableExists($this->tableName);
        }
        if (null !== $criteria) {
            $sql = 'SELECT EXISTS (SELECT * FROM '.$this->prepareFrom().' WHERE '.$this->adapter->driver->prepareCriteria($criteria).');';
        } else {
            $sql = 'SELECT EXISTS ('.$this->toString(false).');';
        }
        if (!($result = $this->adapter->query($sql))) {
            throw $this->adapter->errorException();
        }

        return boolify($result->fetchColumn(0));
    }

    /**
     * Return the current selection as a valid SQL string.
     */
    public function toString(bool $terminate_with_colon = false, bool $untable = false): string
    {
        $sql = 'SELECT';
        if (is_array($this->distinct) && count($this->distinct) > 0) {
            $sql .= ' DISTINCT ON ('.$this->adapter->driver->prepareFields($this->distinct).')';
        } elseif (true === $this->distinct) {
            $sql .= ' DISTINCT';
        }
        if (!is_array($this->fields) || 0 == count($this->fields)) {
            $sql .= ' *';
        } else {
            $sql .= ' '.$this->adapter->driver->prepareFields($this->fields, [], $this->tables());
        }
        // FROM
        $sql .= ' FROM '.(false === $untable ? $this->prepareFrom() : '');
        if (count($this->joins) > 0) {
            foreach ($this->joins as $join) {
                $sql .= ' '.$join['type'].' JOIN '.$this->adapter->driver->field($join['ref']);
                if ($join['alias']) {
                    $sql .= ' '.$join['alias'];
                }
                $sql .= ' ON '.$this->adapter->driver->prepareCriteria($join['on']);
            }
        }
        // WHERE
        if (is_array($this->criteria) && count($this->criteria) > 0) {
            $sql .= ' WHERE '.$this->adapter->driver->prepareCriteria($this->criteria);
        }
        // GROUP BY
        if (count($this->group) > 0) {
            $sql .= ' GROUP BY '.$this->adapter->driver->prepareFields($this->group);
        }
        // HAVING
        if (count($this->having) > 0) {
            $sql .= ' HAVING '.$this->adapter->driver->prepareCriteria($this->having);
        }
        // WINDOW
        if (count($this->window) > 0) {
            $items = [];
            foreach ($this->window as $name => $info) {
                $item = 'PARTITION BY '.$this->adapter->driver->prepareFields((array) $info['as']);
                if ($info['order']) {
                    $item .= ' ORDER BY '.$this->prepareOrder($info['order']);
                }
                $items[] = $name.' AS ( '.$item.' )';
            }
            $sql .= ' WINDOW '.implode(', ', $items);
        }
        // ORDER BY
        if (count($this->order) > 0) {
            $sql .= ' ORDER BY '.$this->prepareOrder($this->order);
        }
        // LIMIT
        if (null !== $this->limit) {
            $sql .= ' LIMIT '.(string) (int) $this->limit;
        }
        // OFFSET
        if (null !== $this->offset) {
            $sql .= ' OFFSET '.(string) (int) $this->offset;
        }
        // FETCH
        if (array_key_exists('which', $this->fetch)) {
            $sql .= ' FETCH';

            if (array_key_exists('which', $this->fetch)) {
                $sql .= ' '.strtoupper($this->fetch['which']);
            }
            if (array_key_exists('count', $this->fetch)) {
                $sql .= ' '.(($this->fetch['count'] > 1) ? $this->fetch['count'].' ROWS' : 'ROW');
            }
        }
        // FOR
        // Combined Queries
        if (2 === count($this->combine)) {
            $sql .= "\n".$this->combine[0]."\n".$this->combine[1];
        }
        if ($terminate_with_colon) {
            $sql .= ';';
        }

        return $sql;
    }

    /**
     * Execute the current selection.
     */
    public function execute(): Result
    {
        if (null === $this->result) {
            DBD\BaseDriver::$selectGroups = [];
            $sql = $this->toString();
            if (!($this->result = $this->adapter->query($sql))) {
                throw $this->adapter->errorException();
            }
            $this->result->setSelectGroups(DBD\BaseDriver::$selectGroups);
        }

        return $this->result;
    }

    /**
     * Prepare a statement for execution and returns a new \Hazaar\Result object.
     *
     * The criteria can contain zero or more names (:name) or question mark (?) parameter markers for which
     * real values will be substituted when the statement is executed. Both named and question mark parameter
     * markers cannot be used within the same statement template; only one or the other parameter style. Use
     * these parameters to bind any user-input, do not include the user-input directly in the query.
     *
     * You must include a unique parameter marker for each value you wish to pass in to the statement when you
     * call \Hazaar\Result::execute(). You cannot use a named parameter marker of the same name more than once
     * in a prepared statement.
     *
     * @param mixed $criteria the query selection criteria
     * @param mixed $fields   the field selection
     */
    public function prepare(mixed $criteria = [], mixed $fields = [], ?string $name = null): Result
    {
        return $this->adapter->prepare($this->find($criteria, $fields)->toString(), $name);
    }

    /**
     * Defined the current field selection definition.
     */
    public function fields(): Table
    {
        $this->fields[] = array_filter(func_get_args(), function ($value) { return !(is_null($value) || (is_string($value) && '' === trim($value))); });

        return $this;
    }

    /**
     * Alias for Hazaar\DBI\Table::fields().
     */
    public function select(): Table
    {
        return call_user_func_array([$this, 'fields'], func_get_args());
    }

    /**
     * Selects only distinct rows that match based on the specified expressions.
     */
    public function distinct(): Table
    {
        $this->distinct = func_num_args() > 0 ? array_merge($this->distinct, func_get_args()) : true;

        return $this;
    }

    /**
     * Defines a WHERE selection criteria.
     */
    public function where(mixed $criteria): Table
    {
        if (is_string($criteria)) {
            $this->criteria[] = $criteria;
        } else {
            $this->criteria = array_merge($this->criteria, (array) $criteria);
        }

        return $this;
    }

    public function group(): Table
    {
        $this->group = array_merge($this->group, func_get_args());

        return $this;
    }

    public function having(): Table
    {
        $this->having = array_merge($this->having, func_get_args());

        return $this;
    }

    /**
     * @param array<string, int>|string $orderBy
     */
    public function window(string $name, string $partitionBy, null|array|string $orderBy = null): Table
    {
        $this->window[$name] = [
            'as' => $partitionBy,
            'order' => $orderBy,
        ];

        return $this;
    }

    /**
     * Join a table to the current query using the provided join criteria.
     *
     * @param string              $references the table to join to the query
     * @param array<mixed>|string $on         The join criteria.  This is mostly just a standard query selection criteria.
     * @param string              $alias      an alias to use for the joined table
     * @param string              $type       the join type such as INNER, OUTER, LEFT, RIGHT, etc
     */
    public function join(string $references, null|array|string $on = null, ?string $alias = null, string $type = 'INNER'): Table
    {
        if (!$type) {
            $type = 'INNER';
        }
        $index = (null === $alias) ? $references.'_'.uniqid() : $alias;
        $this->joins[$index] = [
            'type' => $type,
            'ref' => $references,
            'on' => $on,
            'alias' => $alias,
        ];

        return $this;
    }

    /**
     * @param array<mixed> $on
     */
    public function innerJoin(string $references, null|array|string $on = null, ?string $alias = null): Table
    {
        return $this->join($references, $on, $alias, 'INNER');
    }

    /**
     * @param array<mixed> $on
     */
    public function leftJoin(string $references, null|array|string $on = null, ?string $alias = null): Table
    {
        return $this->join($references, $on, $alias, 'LEFT');
    }

    /**
     * @param array<mixed> $on
     */
    public function rightJoin(string $references, null|array|string $on = null, ?string $alias = null): Table
    {
        return $this->join($references, $on, $alias, 'RIGHT');
    }

    /**
     * @param array<mixed>|string $on
     */
    public function fullJoin(string $references, null|array|string $on = null, ?string $alias = null): Table
    {
        return $this->join($references, $on, $alias, 'FULL');
    }

    /**
     * @param array<string,int>|string $fieldDef
     */
    public function sort(array|string $fieldDef, int $sortFlags = SORT_ASC): Table
    {
        if (!is_array($fieldDef)) {
            $fieldDef = [
                $fieldDef => $sortFlags,
            ];
        }
        $this->order = $fieldDef;

        return $this;
    }

    public function limit(?int $limit = null): int|Table
    {
        if (null === $limit) {
            return $this->limit;
        }
        $this->limit = $limit;

        return $this;
    }

    public function offset(?int $offset = null): int|Table
    {
        if (null === $offset) {
            return $this->offset;
        }
        $this->offset = $offset;

        return $this;
    }

    /**
     * Insert a record into a database table.
     *
     * Using $updateColumns it's possible to perform an "upsert".  An upsert is an INSERT, that
     * when it fails, columns can be updated in the existing row.
     *
     * @param mixed               $fields        the fields to be inserted
     * @param mixed               $returning     a column to return when the row is inserted (usually the primary key)
     * @param array<mixed>|string $updateColumns the names of the columns to be updated if the row exists
     * @param array<mixed>        $updateWhere   Not used yet
     *
     * @return array<mixed>|false|int
     */
    public function insert(
        mixed $fields,
        mixed $returning = null,
        null|array|string $updateColumns = null,
        ?array $updateWhere = null
    ): array|false|int {
        return $this->adapter->insert($this->tableName, $fields, $returning, $updateColumns, $updateWhere, count($this->tables) > 0 ? $this : null);
    }

    /**
     * @return array<mixed>|false|int
     */
    public function update(
        mixed $criteria,
        mixed $fields,
        mixed $returning = null
    ): array|false|int {
        $from = [];
        if (count($this->joins) > 0) {
            foreach ($this->joins as $join) {
                $from[] = $this->adapter->driver->field($join['ref'])
                    .(array_key_exists('alias', $join) ? ' '.$join['alias'] : null);
                $criteria[] = $join['on'];
            }
        }

        return $this->adapter->update($this->tableName.($this->alias ? ' '.$this->alias : null), $fields, $criteria, $from, $returning, $this->tables());
    }

    public function delete(mixed $criteria): false|int
    {
        $from = [];
        if (count($this->joins) > 0) {
            foreach ($this->joins as $join) {
                $from[] = $this->adapter->driver->field($join['ref'])
                    .(array_key_exists('alias', $join) && $join['alias'] ? ' '.$join['alias'] : null);
                $criteria[] = $join['on'];
            }
        }

        return $this->adapter->driver->delete($this->tableName.($this->alias ? ' '.$this->alias : null), $criteria, $from);
    }

    public function deleteAll(): false|int
    {
        return $this->adapter->driver->deleteAll($this->tableName);
    }

    public function row(int $offset = 0): null|Row
    {
        $result = $this->execute();

        return $result->row($offset);
    }

    /**
     * @return array<Row>|false
     */
    public function rows(): array|false
    {
        $result = $this->execute();

        return $result->rows();
    }

    /**
     * @return array<mixed>
     */
    public function fetch(
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $offset = 0,
        bool $clobberDupNamedCols = false
    ): ?array {
        $result = $this->execute();

        return $result->fetch(true !== $clobberDupNamedCols && $result->hasSelectGroups() ? \PDO::FETCH_NAMED : \PDO::FETCH_ASSOC, $cursorOrientation, $offset);
    }

    /**
     * @return array<mixed>
     */
    public function fetchColumn(int $column = 0): array|false
    {
        $result = $this->execute();

        return $result->fetchColumn($column);
    }

    /**
     * @return array<mixed>
     */
    public function fetchAll(
        mixed $fetchArgument = null,
        bool $clobberDupNamedCols = false
    ): array {
        $result = $this->execute();

        return $result->fetchAll(true !== $clobberDupNamedCols && $result->hasSelectGroups() ? \PDO::FETCH_NAMED : \PDO::FETCH_ASSOC, $fetchArgument);
    }

    /**
     * @return array<mixed>
     */
    public function fetchAllColumn(
        string $columnName,
        mixed $fetchArgument = null,
        bool $clobberDupNamedCols = false
    ): array {
        $this->fields = [$columnName];
        $result = $this->execute();
        $data = $result->fetchAll(true !== $clobberDupNamedCols && $result->hasSelectGroups() ? \PDO::FETCH_NAMED : \PDO::FETCH_ASSOC, $fetchArgument);

        return (count($data) > 0) ? array_column($data, key($data[0])) : []; // Returns an empty array for backwards compatibility.
    }

    public function reset(): Row
    {
        $this->execute();

        return $this->result->current();
    }

    // Iterator
    public function current(): Row
    {
        if (!$this->result) {
            $this->execute();
        }

        return $this->result->current();
    }

    public function key(): null|int|string
    {
        if (!$this->result) {
            $this->execute();
        }

        return $this->result->key();
    }

    public function next(): void
    {
        if (!$this->result) {
            $this->execute();
        }
        $this->result->next();
    }

    public function rewind(): void
    {
        if (!$this->result) {
            $this->execute();
        }

        $this->result->rewind();
    }

    public function valid(): bool
    {
        if (!$this->result) {
            $this->execute();
        }

        return $this->result->valid();
    }

    // Countable
    public function count(): int
    {
        if ($this->result) {
            return $this->result->rowCount();
        }
        $sql = 'SELECT count(*) FROM '.$this->prepareFrom();
        if (count($this->joins) > 0) {
            foreach ($this->joins as $join) {
                $sql .= ' '.$join['type'].' JOIN '.$this->adapter->driver->field($join['ref']);
                if ($join['alias']) {
                    $sql .= ' '.$join['alias'];
                }
                $sql .= ' ON '.$this->adapter->driver->prepareCriteria($join['on']);
            }
        }
        if ($this->criteria) {
            $sql .= ' WHERE '.$this->adapter->driver->prepareCriteria($this->criteria);
        }
        if (!($result = $this->adapter->query($sql))) {
            throw new \Exception(ake($this->adapter->errorInfo(), 2));
        }

        return (int) $result->fetchColumn(0);
    }

    public function getResult(): Result
    {
        if (!$this->result) {
            $this->execute();
        }

        return $this->result;
    }

    /**
     * Collates a result into a simple key/value array.
     *
     * This is useful for generating SELECT lists directly from a resultset.
     *
     * @param string $indexColumn the column to use as the array index
     * @param string $valueColumn the column to use as the array value
     * @param string $groupColumn optional column name to group items by
     *
     * @return array<string, mixed>
     */
    public function collate(string $indexColumn, ?string $valueColumn = null, ?string $groupColumn = null): array
    {
        return array_collate($this->fetchAll(), $indexColumn, $valueColumn, $groupColumn);
    }

    /**
     * Truncate the table.
     *
     * Truncating a table quickly removes all rows from a set of tables. It has the same effect as Hazaar\DBI\Table::deleteAll() on
     * each table, but since it does not actually scan the tables it is faster. Furthermore, it reclaims disk space
     * immediately, rather than requiring a subsequent VACUUM operation. This is most useful on large tables.
     *
     * @param bool $only            Only the named table is truncated. If FALSE, the table and all its descendant tables (if any) are truncated.
     * @param bool $restartIdentity Automatically restart sequences owned by columns of the truncated table(s).  The default is to no restart.
     * @param bool $cascade         If TRUE, automatically truncate all tables that have foreign-key references to any of the named tables, or
     *                              to any tables added to the group due to CASCADE.  If FALSE, Refuse to truncate if any of the tables have
     *                              foreign-key references from tables that are not listed in the command. FALSE is the default.
     */
    public function truncate(bool $only = false, bool $restartIdentity = false, bool $cascade = false): bool
    {
        return $this->adapter->truncate($this->tableName, $only, $restartIdentity, $cascade);
    }

    /**
     * List all tables that will be accessed in this table query.
     *
     * Returns an array of table names of all tables used in this query, including joins.
     *
     * @return array<int, string>
     */
    public function listUsedTables(): array
    {
        $tables = [$this->tableName];
        if (is_array($this->joins)) {
            foreach ($this->joins as $join) {
                $tables[] = $join['ref'];
            }
        }

        return $tables;
    }

    /**
     * Add a table or function to the FROM table reference.
     *
     * PostgreSQL combines table references using a cross-join.
     *
     * See: [here](https://www.postgresql.org/docs/11/queries-table-expressions.html#QUERIES-FROM) for examples.
     */
    public function from(string $table, ?string $alias = null): Table
    {
        if ($alias) {
            $this->tables[$alias] = $table;
        } else {
            $this->tables[] = $table;
        }

        return $this;
    }

    /**
     * Append a table query using a UNION.
     *
     * This will return results from both queries combined together.
     */
    public function union(Table $query): Table
    {
        $this->combine = ['UNION', $query];

        return $this;
    }

    /**
     * Append a table query using an INTERSECT.
     *
     * This will return only results that exist in both queries.
     */
    public function intersect(Table $query): Table
    {
        $this->combine = ['INTERSECT', $query];

        return $this;
    }

    /**
     * Append a table query using an EXCEPT.
     *
     * This will return results from the first query except if they appear in the second.
     */
    public function except(Table $query): Table
    {
        $this->combine = ['EXCEPT', $query];

        return $this;
    }

    private function prepareFrom(): string
    {
        $tables = $this->tableName ? array_merge([$this->alias ?? $this->tableName => $this->tableName], $this->tables) : $this->tables;
        foreach ($tables as $alias => &$table) {
            if ($table instanceof Table) {
                $schemaTable = '('.$table.')';
                if (!(is_string($alias) && $alias)) {
                    $alias = '_'.uniqid().'_';
                }
            } elseif (false === strpos($table, '(')) {
                $schemaTable = $this->adapter->schemaName($table);
            } else {
                $schemaTable = (string) $table;
            }
            if (is_string($alias) && $alias !== $table) {
                $schemaTable .= ' AS '.$alias;
            }
            $table = $schemaTable;
        }

        return implode(', ', $tables);
    }

    /**
     * @return array<string>
     */
    private function tables(): array
    {
        $alias = ($this->alias) ? $this->alias : $this->tableName;
        $tables = [$alias => $this->tableName];
        foreach ($this->joins as $alias => $join) {
            $tables[$alias] = $join['ref'];
        }

        return $tables;
    }

    /**
     * @param array<mixed>|string $orderDefinition
     */
    private function prepareOrder(array|string $orderDefinition): string
    {
        $order = [];
        if (is_string($orderDefinition)) {
            $order[] = $orderDefinition;
        } elseif (is_array($orderDefinition)) {
            foreach ($orderDefinition as $field => $mode) {
                $name = ake(DBD\BaseDriver::$selectGroups, $this->tableName, $this->tableName).'.'.$field;
                if ($key = array_search($name, DBD\BaseDriver::$selectGroups)) {
                    $field = $key;
                }
                if (is_array($mode)) {
                    $nulls = ake($mode, '$nulls', 0);
                    $mode = ake($mode, '$dir', 1);
                } else {
                    $nulls = 0;
                }
                $order[] = $field.' '.(match ($mode) {
                    SORT_DESC => 'DESC', default => 'ASC',
                }).' NULLS '.(($nulls > 0) ? 'FIRST' : 'LAST');
            }
        }

        return implode(', ', $order);
    }
}
