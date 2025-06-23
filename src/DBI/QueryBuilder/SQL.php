<?php

declare(strict_types=1);

namespace Hazaar\DBI\QueryBuilder;

use Hazaar\DBI\DBD\Enums\QueryType;
use Hazaar\DBI\Interface\QueryBuilder;
use Hazaar\DBI\Table;
use Hazaar\Model;
use Hazaar\Util\Arr;

class SQL implements QueryBuilder
{
    protected QueryType $type = QueryType::SELECT;

    /**
     * @var array<string>
     */
    protected array $selectGroups = [];
    protected string $quoteSpecial = '"';

    /**
     * @var array<string>
     */
    protected array $reservedWords = [];

    /**
     * The fields to select, update or insert.
     *
     * This can be a bunch of things, like an array, strings, Table or even a Model or stdClass.
     */
    protected mixed $fields = [];

    /**
     * @var array<string>
     */
    protected array $primaryTable = [];

    /**
     * @var array<string|Table>
     */
    protected array $tables = [];

    /**
     * @var array<string>
     */
    protected array $where = [];

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

    protected mixed $returning = false;
    protected bool $cascade = false;

    /**
     * @var array<string>|bool
     */
    protected array|bool $distinct = false;
    protected ?int $limit = null;
    protected ?int $offset = null;

    /**
     * @var null|array<string>|string
     */
    protected null|array|string $conflictTarget = null;

    /**
     * @var null|array<string>|bool
     */
    protected null|array|bool $conflictUpdate = false;

    private ?string $schemaName;

    /**
     * @var array<string,mixed>
     */
    private array $valueIndex = [];

    public function __construct(?string $schemaName = null)
    {
        $this->schemaName = $schemaName;
    }

    public function __toString(): string
    {
        return $this->toString(terminateWithColon: true);
    }

    public function setReservedWords(array $words): void
    {
        $this->reservedWords = $words;
    }

    public function getSchemaName(): ?string
    {
        return $this->schemaName;
    }

    /**
     * @return array<int,string>
     */
    public function parseSchemaName(string $tableName): array
    {
        $schemaName = $this->schemaName;
        if (false !== strpos($tableName, '.')) {
            [$schema, $tableName] = explode('.', $tableName);
        }

        return [$schemaName, $tableName];
    }

    /**
     * @param array{string,string}|string $tableName
     */
    public function schemaName(array|string $tableName): string
    {
        [$tableName, $alias] = is_array($tableName) ? $tableName : [$tableName, null];
        // Check if there is an alias in the table name
        if (($pos = strpos($tableName, ' ')) !== false) {
            [$tableName, $alias] = preg_split('/\s*(?<=.{'.$pos.'})\s*/', $tableName, 2);
        }
        // Check if we already have a schemaName defined
        if (null !== $this->schemaName && false === strpos($tableName, '.')) {
            $tableName = $this->schemaName.'.'.$tableName;
        }

        return $this->quoteSpecial($tableName).($alias ? ' '.$this->quoteSpecial($alias) : '');
    }

    public function quote(string $string, bool $addSlashes = true): string
    {
        return '\''.($addSlashes ? addcslashes($string, "'") : $string).'\'';
    }

    public function quoteSpecial(mixed $value): mixed
    {
        if (false === is_string($value)) {
            return $value;
        }
        $parts = explode('.', $value);
        array_walk($parts, function (&$item) {
            $item = $this->quoteSpecial.$item.$this->quoteSpecial;
        });

        return implode('.', $parts);
    }

    public function reset(): self
    {
        $this->type = QueryType::SELECT;
        $this->selectGroups = [];
        $this->primaryTable = [];
        $this->tables = [];
        $this->where = [];
        $this->fields = [];
        $this->group = [];
        $this->having = [];
        $this->window = [];
        $this->joins = [];
        $this->combine = [];
        $this->order = [];
        $this->fetch = [];
        $this->distinct = false;
        $this->returning = false;
        $this->cascade = false;
        $this->limit = null;
        $this->offset = null;
        $this->valueIndex = [];

        return $this;
    }

    public function create(string $name, string $type, bool $ifNotExists = false): string
    {
        $sql = 'CREATE '.strtoupper($type).' ';
        if ($ifNotExists) {
            $sql .= 'IF NOT EXISTS ';
        }
        $sql .= $name;

        return $sql;
    }

    public function insert(mixed $fields): self
    {
        $this->type = QueryType::INSERT;
        $this->fields = $fields;

        return $this;
    }

    public function update(array $fields): self
    {
        $this->type = QueryType::UPDATE;
        $this->fields = $fields;

        return $this;
    }

    public function delete(): self
    {
        $this->type = QueryType::DELETE;

        return $this;
    }

    public function truncate(bool $cascade = false): self
    {
        $this->type = QueryType::TRUNCATE;
        $this->cascade = $cascade;

        return $this;
    }

    public function count(): string
    {
        $this->fields = ['COUNT(*)'];

        return $this->toString();
    }

    public function exists(string $tableName, mixed $criteria = null): string
    {
        return 'SELECT EXISTS ('.$this->select('1')->from($tableName)->where($criteria)->toString().')';
    }

    public function select(mixed ...$columns): self
    {
        $this->type = QueryType::SELECT;
        $this->fields = array_filter($columns, function ($value) {
            return !(is_null($value) || (is_string($value) && '' === trim($value)));
        });

        return $this;
    }

    /**
     * Selects only distinct rows that match based on the specified expressions.
     */
    public function distinct(string ...$columns): self
    {
        $this->distinct = count($columns) > 0 ? $columns : true;

        return $this;
    }

    public function from(string $table, ?string $alias = null): self
    {
        return $this->table($table, $alias);
    }

    public function table(string $table, ?string $alias = null): self
    {
        $this->primaryTable = [$table, $alias];

        return $this;
    }

    /**
     * Defines a WHERE selection criteria.
     */
    public function where(array|string $criteria): self
    {
        if (!is_array($criteria)) {
            $criteria = [$criteria];
        }
        $this->where = $criteria;

        return $this;
    }

    public function group(string ...$columns): self
    {
        $this->group = $columns;

        return $this;
    }

    public function having(string ...$columns): self
    {
        $this->having = $columns;

        return $this;
    }

    /**
     * @param array<string, int>|string $orderBy
     */
    public function window(string $name, string $partitionBy, null|array|string $orderBy = null): self
    {
        $this->window[$name] = [
            'as' => $partitionBy,
            'order' => $orderBy,
        ];

        return $this;
    }

    public function join(
        string $references,
        null|array|string $on = null,
        ?string $alias = null,
        string $type = 'INNER'
    ): self {
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

    public function order(array|string $fieldDef, int $sortDirection = SORT_ASC): self
    {
        if (!is_array($fieldDef)) {
            $fieldDef = [
                $fieldDef => $sortDirection,
            ];
        }
        $this->order = $fieldDef;

        return $this;
    }

    public function limit(?int $limit = null): int|self
    {
        if (null === $limit) {
            return $this->limit;
        }
        $this->limit = $limit;

        return $this;
    }

    public function offset(?int $offset = null): int|self
    {
        if (null === $offset) {
            return $this->offset;
        }
        $this->offset = $offset;

        return $this;
    }

    /**
     * Return the current selection as a valid SQL string.
     */
    public function toString(bool $terminateWithColon = false): string
    {
        $this->valueIndex = [];

        return match ($this->type) {
            QueryType::SELECT => $this->toSELECTString(),
            QueryType::INSERT => $this->toINSERTString(),
            QueryType::UPDATE => $this->toUPDATEString(),
            QueryType::DELETE => $this->toDELETEString(),
            QueryType::TRUNCATE => $this->toTRUNCATEString(),
        }.($terminateWithColon ? ';' : '');
    }

    public function field(string $string): string
    {
        if (in_array(strtoupper($string), $this->reservedWords)) {
            $string = $this->quoteSpecial($string);
        }

        return $string;
    }

    /**
     * @param array<string> $exclude
     * @param array<string> $tables
     */
    public function prepareFields(mixed $fields, array $exclude = [], array $tables = []): string
    {
        if (!is_array($fields)) {
            return $this->field($fields);
        }
        $fieldDef = [];
        foreach ($fields as $key => $value) {
            if (is_string($value) && in_array($value, $exclude)) {
                $fieldDef[] = $value;
            } elseif (is_numeric($key)) {
                $fieldDef[] = is_array($value) ? $this->prepareFields($value, [], $tables) : $this->field($value);
            } elseif (is_array($value)) {
                $fields = [];
                $fieldMap = Arr::toDotNotation([$key => $this->prepareArrayAliases($value)]);
                foreach ($fieldMap as $alias => $field) {
                    if (preg_match('/^((\w+)\.)?\*$/', trim($field), $matches) > 0) {
                        if (count($matches) > 1) {
                            $alias = $tables[$matches[2]] ?? null;
                        } else {
                            $alias = reset($tables);
                            $value = key($tables).'.*';
                        }
                        $this->selectGroups[$alias] = $key;
                        $fieldDef[] = $this->field($field);

                        continue;
                    }
                    $lookup = md5(uniqid('dbi_', true));
                    $this->selectGroups[$lookup] = $alias;
                    $fields[$lookup] = $field;
                }
                $fieldDef[] = $this->prepareFields($fields, [], $tables);
            } elseif (is_string($value) && preg_match('/^((\w+)\.)?\*$/', trim($value), $matches) > 0) {
                if (count($matches) > 1) {
                    $alias = $tables[$matches[2]] ?? null;
                } else {
                    $alias = reset($tables);
                    $value = key($tables).'.*';
                }
                $this->selectGroups[$alias] = $key;
                $fieldDef[] = $this->field($value);
            } else {
                $fieldDef[] = $this->field($value).' AS '.$this->field($key);
            }
        }

        return implode(', ', $fieldDef);
    }

    public function prepareValue(string $key, mixed $value): mixed
    {
        if (isset($this->valueIndex[$key])) {
            if (in_array($value, $this->valueIndex[$key])) {
                $index = array_search($value, $this->valueIndex[$key]);
            } else {
                $index = count($this->valueIndex[$key]);
            }
            $key = ":{$key}{$index}";
        } else {
            $this->valueIndex[$key] = [$value];
            $index = 0;
            $key = ":{$key}{$index}";
        }

        return $key;
    }

    /**
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    public function prepareArrayAliases(array $array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->prepareArrayAliases($value);
            } elseif (is_string($value) && '*' === substr($value, -1)) {
                continue;
            }
            if (!is_numeric($key)) {
                continue;
            }
            unset($array[$key]);
            $key = $value;
            if (($pos = strrpos($key, '.')) > 0) {
                $key = substr($key, $pos + 1);
            }
            $array[$key] = $value;
        }

        return $array;
    }

    /**
     * @param array<mixed> $criteria
     */
    public function prepareCriteria(
        array|string $criteria,
        string $bindType = 'AND',
        string $tissue = '=',
        ?string $parentRef = null,
        bool &$setKey = true,
        int $depth = 0,
    ): string {
        if (!is_array($criteria)) {
            return $criteria;
        }
        $parts = [];
        if (0 === count($criteria)) {
            return 'TRUE';
        }
        foreach ($criteria as $key => $value) {
            if ($value instanceof Table) {
                $value = '('.$value->toString().' )';
            }
            if (is_int($key) && is_string($value)) {
                $parts[] = '( '.$value.' )';
            } elseif (is_string($key) && '$' == substr($key, 0, 1)) {
                $parentRef = $parentRef ?? $key;
                if ($actionParts = $this->prepareCriteriaAction(strtolower(substr($key, 1)), $value, $parentRef, $tissue, $setKey, depth: $depth)) {
                    if (is_array($actionParts)) {
                        $parts = array_merge($parts, $actionParts);
                    } else {
                        $parts[] = $actionParts;
                    }
                } else {
                    $parts[] = ' '.$tissue.' '.$this->prepareCriteria(criteria: $value, bindType: strtoupper(substr($key, 1)), depth: $depth + 1);
                }
            } elseif (is_array($value)) {
                $parentRef = $parentRef ?? is_string($key) ? $key : null;
                $set = true;
                $subValue = $this->prepareCriteria($value, $bindType, $tissue, $parentRef, $set, depth: $depth + 1);
                if (is_numeric($key)) {
                    $parts[] = $subValue;
                } else {
                    $parts[] = ((true === $set) ? $key.' ' : '').$subValue;
                }
            } else {
                if ($parentRef && false === strpos($key, '.')) {
                    $key = $parentRef.'.'.$key;
                }
                $parts[] = $this->field($key).' '.$tissue.' '.$this->prepareValue($key, value: $value);
            }
        }
        $encapsulate = (count($parts) > 1) && ($depth > 0);
        $sql = ($encapsulate ? '( ' : null).implode(" {$bindType} ", $parts).($encapsulate ? ' )' : null);

        return $sql;
    }

    public function getCriteriaValues(): array
    {
        $criteriaValues = [];
        foreach ($this->valueIndex as $key => $values) {
            foreach ($values as $index => $value) {
                $criteriaValues["{$key}{$index}"] = $value;
            }
        }

        return $criteriaValues;
    }

    public function returning(mixed ...$columns): self
    {
        $this->returning = 1 === count($columns) ? $columns[0] : $columns;

        return $this;
    }

    public function onConflict(
        null|array|string $target = null,
        null|array|bool $update = null
    ): self {
        $this->conflictTarget = $target;
        $this->conflictUpdate = $update;

        return $this;
    }

    /**
     * @return null|array<string>|string
     */
    private function prepareCriteriaAction(
        string $action,
        mixed $value,
        string $key,
        ?string $tissue = '=',
        bool &$setKey = true,
        int $depth = 0,
    ): null|array|string {
        switch ($action) {
            case 'and':
                return $this->prepareCriteria(criteria: $value, bindType: 'AND', depth: $depth);

            case 'or':
                return $this->prepareCriteria(criteria: $value, bindType: 'OR', depth: $depth);

            case 'ne':
                return (is_bool($value) ? 'IS NOT ' : '!= ').$this->prepareValue($key, $value);

            case 'not':
                return 'NOT ('.$this->prepareCriteria(criteria: $value, depth: $depth).')';

            case 'null':
                return $this->quoteSpecial($value).' IS NULL';

            case 'notnull':
                return $this->quoteSpecial($value).' IS NOT NULL';

            case 'ref':
                return $tissue.' '.$value;

            case 'nin':
            case 'in':
                if (is_array($value)) {
                    if (0 === count($value)) {
                        throw new \Exception('$in requires non-empty array value');
                    }
                    $values = [];
                    foreach ($value as $val) {
                        $values[] = $this->prepareValue($key, $val);
                    }
                    $value = implode(', ', $values);
                }

                return (('nin' == $action) ? 'NOT ' : null).'IN ('.$value.')';

            case 'gt':
                return '> '.$this->prepareValue($key, $value);

            case 'gte':
                return '>= '.$this->prepareValue($key, $value);

            case 'lt':
                return '< '.$this->prepareValue($key, $value);

            case 'lte':
                return '<= '.$this->prepareValue($key, $value);

            case 'ilike': // iLike
                return 'ILIKE '.$this->prepareValue($key, $value);

            case 'like': // Like
                return 'LIKE '.$this->prepareValue($key, $value);

            case 'bt':
                if (($count = count($value)) !== 2) {
                    throw new \Exception('DBD: $bt operator requires array argument with exactly 2 elements. '.$count.' given.');
                }

                return 'BETWEEN '.$this->prepareValue($key, array_values($value)[0])
                    .' AND '.$this->prepareValue($key, array_values($value)[1]);

            case '~':
            case '~*':
            case '!~':
            case '!~*':
                return $action.' '.$this->prepareValue($key, $value);

            case 'exists': // exists
                $parts = [];
                foreach ($value as $table => $criteria) {
                    $parts[] = 'EXISTS ( SELECT * FROM '.$table.' WHERE '.$this->prepareCriteria($criteria).' )';
                }

                return $parts;

            case 'sub': // sub query
                return '('.$value[0]->toString(false).') '.$this->prepareCriteria($value[1]);

            case 'json':
                return $this->prepareValue($key, json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return null;
    }

    /**
     * @return array<string>
     */
    private function tables(): array
    {
        if (!$this->primaryTable) {
            return [];
        }
        $tables = [$this->primaryTable[1] ?? $this->primaryTable[0] => $this->primaryTable[0]];
        foreach ($this->joins as $alias => $join) {
            $tables[$alias] = $join['ref'];
        }

        return $tables;
    }

    private function prepareFrom(): string
    {
        $tables = $this->primaryTable ? array_merge([$this->primaryTable[1] ?? $this->primaryTable[0] => $this->primaryTable[0]], $this->tables) : $this->tables;
        foreach ($tables as $alias => &$table) {
            if ($table[0] instanceof Table) {
                $schemaTable = '('.$table.')';
                if (!(is_string($alias) && $alias)) {
                    $alias = '_'.uniqid().'_';
                }
            } elseif (false === strpos($table, '(')) { // Check if the table is a sub query or function
                $schemaTable = $this->schemaName($table);
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
     * @param array<mixed>|string $orderDefinition
     */
    private function prepareOrder(array|string $orderDefinition): string
    {
        $order = [];
        if (is_string($orderDefinition)) {
            $order[] = $orderDefinition;
        } elseif (is_array($orderDefinition)) {
            foreach ($orderDefinition as $field => $mode) {
                $name = $this->selectGroups[$this->primaryTable[0]] ?? $this->primaryTable[0].'.'.$field;
                if ($key = array_search($name, $this->selectGroups)) {
                    $field = $key;
                }
                if (is_array($mode)) {
                    $nulls = $mode['$nulls'] ?? 0;
                    $mode = $mode['$dir'] ?? 1;
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

    private function toSELECTString(): string
    {
        $sql = 'SELECT';
        if (is_array($this->distinct) && count($this->distinct) > 0) {
            $sql .= ' DISTINCT ON ('.$this->prepareFields($this->distinct).')';
        } elseif (true === $this->distinct) {
            $sql .= ' DISTINCT';
        }
        if (!is_array($this->fields) || 0 == count($this->fields)) {
            $sql .= ' *';
        } else {
            $sql .= ' '.$this->prepareFields($this->fields, [], $this->tables());
        }
        // FROM
        if ($from = $this->prepareFrom()) {
            $sql .= ' FROM '.$from;
        }
        if (count($this->joins) > 0) {
            foreach ($this->joins as $join) {
                $sql .= ' '.$join['type'].' JOIN '.$this->quoteSpecial($join['ref']);
                if ($join['alias']) {
                    $sql .= ' '.$join['alias'];
                }
                $sql .= ' ON '.$this->prepareCriteria(criteria: $join['on']);
            }
        }
        // WHERE
        if (count($this->where) > 0) {
            $sql .= ' WHERE '.$this->prepareCriteria($this->where);
        }
        // GROUP BY
        if (count($this->group) > 0) {
            $sql .= ' GROUP BY '.$this->prepareFields($this->group);
        }
        // HAVING
        if (count($this->having) > 0) {
            $sql .= ' HAVING '.$this->prepareCriteria($this->having);
        }
        // WINDOW
        if (count($this->window) > 0) {
            $items = [];
            foreach ($this->window as $name => $info) {
                $item = 'PARTITION BY '.$this->prepareFields((array) $info['as']);
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

        return $sql;
    }

    private function toINSERTString(): string
    {
        $sql = 'INSERT INTO '.$this->schemaName($this->primaryTable);
        if ($this->fields instanceof Model) {
            $this->fields = $this->fields->toArray('dbiWrite', 0);
        } elseif ($this->fields instanceof \stdClass) {
            $this->fields = (array) $this->fields;
        }
        if ($this->fields instanceof Table) {
            $sql .= ' '.(string) $this->fields;
        } else {
            $fieldDef = array_keys($this->fields);
            foreach ($fieldDef as &$field) {
                $field = $this->field($field);
            }
            $valueDef = array_values($this->fields);
            foreach ($valueDef as $key => &$value) {
                $value = $this->prepareValue(key: $fieldDef[$key], value: $value);
            }
            $sql .= ' ('.implode(', ', $fieldDef).') VALUES ('.implode(', ', $valueDef).')';
        }
        if (null !== $this->conflictTarget) {
            $sql .= ' ON CONFLICT('.$this->prepareFields($this->conflictTarget).')';
            if (false === $this->conflictUpdate) {
                $sql .= ' DO NOTHING';
            } else {
                if (true === $this->conflictUpdate) {
                    $conflictUpdate = array_keys($this->fields);
                }
                if (is_array($this->conflictUpdate) && count($this->conflictUpdate) > 0) {
                    $updateDefs = [];
                    foreach ($this->conflictUpdate as $index => $field) {
                        if (is_int($index)) {
                            if (!array_key_exists($field, $this->fields) || $field === $this->conflictTarget) {
                                continue;
                            }
                            $updateDefs[] = $this->field($field).' = EXCLUDED.'.$field;
                        } else {
                            $updateDefs[] = $this->field($index).' = '.$field;
                        }
                    }
                    if (count($updateDefs) > 0) {
                        $sql .= ' DO UPDATE SET '.implode(', ', $updateDefs);
                    } else {
                        $sql .= ' DO NOTHING';
                    }
                }
            }
        }
        if (is_string($this->returning)) {
            $this->returning = trim($this->returning);
            $sql .= ' RETURNING '.$this->field($this->returning);
        } elseif (is_array($this->returning) && count($this->returning) > 0) {
            $sql .= ' RETURNING '.$this->prepareFields($this->returning);
        }

        return $sql;
    }

    private function toUPDATEString(): string
    {
        if ($this->fields instanceof Model) {
            $this->fields = $this->fields->toArray('dbiWrite');
        } elseif ($this->fields instanceof \stdClass) {
            $this->fields = (array) $this->fields;
        }
        $fieldDef = [];
        foreach ($this->fields as $key => &$value) {
            $fieldDef[] = $this->field($key).' = '.$this->prepareValue(key: $key, value: $value);
        }
        if (0 == count($fieldDef)) {
            throw new Exception\NoUpdate();
        }
        $sql = 'UPDATE '.$this->schemaName(implode(' ', $this->primaryTable)).' SET '.implode(', ', $fieldDef);
        if (count($this->tables) > 0) {
            $sql .= ' FROM '.implode(', ', $this->tables);
        }
        if (count($this->where) > 0) {
            $sql .= ' WHERE '.$this->prepareCriteria($this->where);
        }
        $returning = (true === $this->returning) ? '*' : $this->returning;
        if (is_string($returning)) {
            $returning = trim($returning);
            $sql .= ' RETURNING '.$this->field($returning);
        } elseif (is_array($returning) && count($returning) > 0) {
            $sql .= ' RETURNING '.$this->prepareFields($returning, [], $this->tables);
        }

        return $sql;
    }

    private function toDELETEString(): string
    {
        $sql = 'DELETE FROM '.$this->schemaName($this->primaryTable);
        if (count($this->tables) > 0) {
            $sql .= ' USING '.$this->prepareFields($this->tables);
        }

        return $sql.' WHERE '.$this->prepareCriteria($this->where);
    }

    private function toTRUNCATEString(): string
    {
        $sql = 'TRUNCATE TABLE '.$this->schemaName($this->primaryTable);
        if ($this->cascade) {
            $sql .= ' CASCADE';
        }

        return $sql;
    }
}
