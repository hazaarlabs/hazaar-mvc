<?php

declare(strict_types=1);

namespace Hazaar\DBI\QueryBuilder;

use Hazaar\DateTime;
use Hazaar\DBI\Interface\QueryBuilder;
use Hazaar\DBI\Table;
use Hazaar\Model;

class SQL implements QueryBuilder
{
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
     * @var array<int,string>
     */
    protected array $from = [];

    /**
     * @var array<Table>
     */
    protected array $tables = [];

    /**
     * @var array<string>
     */
    protected array $where = [];

    /**
     * @var array<string>|string
     */
    protected array|string $columns = [];

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
    private ?string $schemaName;

    public function __construct(?string $schemaName = null)
    {
        $this->schemaName = $schemaName;
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
            list($schema, $tableName) = explode('.', $tableName);
        }

        return [$schemaName, $tableName];
    }

    public function schemaName(string $tableName): string
    {
        $alias = null;
        // Check if there is an alias
        if (($pos = strpos($tableName, ' ')) !== false) {
            list($tableName, $alias) = preg_split('/\s*(?<=.{'.$pos.'})\s*/', $tableName, 2);
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
        $this->selectGroups = [];
        $this->from = [];
        $this->tables = [];
        $this->where = [];
        $this->columns = [];
        $this->group = [];
        $this->having = [];
        $this->window = [];
        $this->joins = [];
        $this->combine = [];
        $this->order = [];
        $this->fetch = [];
        $this->distinct = false;
        $this->limit = null;
        $this->offset = null;

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

    public function insert(
        string $tableName,
        mixed $fields,
        null|array|string $returning = null,
        null|array|string $conflictTarget = null,
        mixed $conflictUpdate = null,
        ?Table $table = null
    ): string {
        $sql = 'INSERT INTO '.$this->schemaName($tableName);
        if ($fields instanceof Model) {
            $fields = $fields->toArray('dbiWrite', 0);
        } elseif ($fields instanceof \stdClass) {
            $fields = (array) $fields;
        }
        if ($fields instanceof Table) {
            $sql .= ' '.(string) $fields;
        } elseif ($table instanceof Table) {
            $fieldDef = [];
            foreach ($fields as $key => $fieldName) {
                $fieldDef[] = $this->field($key);
            }
            $sql .= ' ('.implode(', ', $fieldDef).' ) '.(string) $table->toString();
        } else {
            $fieldDef = array_keys($fields);
            foreach ($fieldDef as &$field) {
                $field = $this->field($field);
            }
            $valueDef = array_values($fields);
            foreach ($valueDef as $key => &$value) {
                $value = $this->prepareValue($value, $fieldDef[$key]);
            }
            $sql .= ' ('.implode(', ', $fieldDef).') VALUES ('.implode(', ', $valueDef).')';
        }
        if (null !== $conflictTarget) {
            $sql .= ' ON CONFLICT('.$this->prepareFields($conflictTarget).')';
            if (null === $conflictUpdate) {
                $sql .= ' DO NOTHING';
            } else {
                if (true === $conflictUpdate) {
                    $conflictUpdate = array_keys($fields);
                }
                if (is_array($conflictUpdate) && count($conflictUpdate) > 0) {
                    $updateDefs = [];
                    foreach ($conflictUpdate as $index => $field) {
                        if (is_int($index)) {
                            if (!array_key_exists($field, $fields) || $field === $conflictTarget) {
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
        if (is_string($returning)) {
            $returning = trim($returning);
            $sql .= ' RETURNING '.$this->field($returning);
        } elseif (is_array($returning) && count($returning) > 0) {
            $sql .= ' RETURNING '.$this->prepareFields($returning);
        }

        return $sql;
    }

    public function update(
        string $tableName,
        mixed $fields,
        array $criteria = [],
        array $from = [],
        mixed $returning = null,
        array $tables = []
    ): string {
        if ($fields instanceof Model) {
            $fields = $fields->toArray('dbiWrite');
        } elseif ($fields instanceof \stdClass) {
            $fields = (array) $fields;
        }
        $fieldDef = [];
        foreach ($fields as $key => &$value) {
            $fieldDef[] = $this->field($key).' = '.$this->prepareValue($value, $key);
        }
        if (0 == count($fieldDef)) {
            throw new Exception\NoUpdate();
        }
        $sql = 'UPDATE '.$this->schemaName($tableName).' SET '.implode(', ', $fieldDef);
        if (count($from) > 0) {
            $sql .= ' FROM '.implode(', ', $from);
        }
        if (count($criteria) > 0) {
            $sql .= ' WHERE '.$this->prepareCriteria($criteria);
        }
        $returnValue = false;
        if (true === $returning) {
            $returning = '*';
        }
        if (is_string($returning)) {
            $returning = trim($returning);
            $sql .= ' RETURNING '.$this->field($returning);
        } elseif (is_array($returning) && count($returning) > 0) {
            $sql .= ' RETURNING '.$this->prepareFields($returning, [], $tables);
        }

        return $sql;
    }

    /**
     * @param array<string> $from
     */
    public function delete(string $tableName, mixed $criteria, array $from = []): string
    {
        $sql = 'DELETE FROM '.$this->schemaName($tableName);
        if (count($from) > 0) {
            $sql .= ' USING '.$this->prepareFields($from);
        }

        return $sql.' WHERE '.$this->prepareCriteria($criteria);
    }

    public function truncate(string $tableName, bool $cascade = false): string
    {
        $sql = 'TRUNCATE TABLE '.$this->schemaName($tableName);
        if ($cascade) {
            $sql .= ' CASCADE';
        }

        return $sql;
    }

    public function count(): string
    {
        $this->columns = ['COUNT(*)'];

        return $this->toString();
    }

    public function exists(string $tableName, mixed $criteria = null): string
    {
        return 'SELECT EXISTS ('.$this->select('1')->from($tableName)->where($criteria)->toString().')';
    }

    public function select(mixed ...$columns): self
    {
        $this->columns = array_filter($columns, function ($value) {
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
        $this->from = [$table, $alias];

        return $this;
    }

    /**
     * Defines a WHERE selection criteria.
     */
    public function where(mixed ...$criteria): self
    {
        $this->where = array_merge($this->where, $criteria);

        return $this;
    }

    public function group(string ...$columns): self
    {
        $this->group = array_merge($this->group, $columns);

        return $this;
    }

    public function having(string ...$columns): self
    {
        $this->having = array_merge($this->having, $columns);

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
    public function toString(bool $terminateWith_colon = false, bool $untable = false): string
    {
        $sql = 'SELECT';
        if (is_array($this->distinct) && count($this->distinct) > 0) {
            $sql .= ' DISTINCT ON ('.$this->prepareFields($this->distinct).')';
        } elseif (true === $this->distinct) {
            $sql .= ' DISTINCT';
        }
        if (!is_array($this->columns) || 0 == count($this->columns)) {
            $sql .= ' *';
        } else {
            $sql .= ' '.$this->prepareFields($this->columns, [], $this->tables());
        }
        // FROM
        if ($from = $this->prepareFrom()) {
            $sql .= ' FROM '.$from;
        }
        if (count($this->joins) > 0) {
            foreach ($this->joins as $join) {
                $sql .= ' '.$join['type'].' JOIN '.$this->field($join['ref']);
                if ($join['alias']) {
                    $sql .= ' '.$join['alias'];
                }
                $sql .= ' ON '.$this->prepareCriteria($join['on']);
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
        if ($terminateWith_colon) {
            $sql .= ';';
        }

        return $sql;
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
                $fieldMap = array_to_dot_notation([$key => $this->prepareArrayAliases($value)]);
                foreach ($fieldMap as $alias => $field) {
                    if (preg_match('/^((\w+)\.)?\*$/', trim($field), $matches) > 0) {
                        if (count($matches) > 1) {
                            $alias = ake($tables, $matches[2]);
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
            } elseif (preg_match('/^((\w+)\.)?\*$/', trim($value), $matches) > 0) {
                if (count($matches) > 1) {
                    $alias = ake($tables, $matches[2]);
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

    public function prepareValues(mixed $values): string
    {
        if (!is_array($values)) {
            $values = [$values];
        }
        foreach ($values as &$value) {
            $value = $this->prepareValue($value);
        }

        return implode(',', $values);
    }

    public function prepareValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value) && count($value) > 0) {
            $value = $this->prepareCriteria($value, null, null, null, $key);
        } elseif ($value instanceof DateTime) {
            $value = $this->quote($value->format('Y-m-d H:i:s'));
        } elseif (is_null($value) || (is_array($value) && 0 === count($value))) {
            $value = 'NULL';
        } elseif (is_bool($value)) {
            $value = ($value ? 'TRUE' : 'FALSE');
        } elseif ($value instanceof \stdClass) {
            $value = $this->quote(json_encode($value));
        } elseif ($value instanceof Model) {
            $value = $this->quote((string) json_encode($value->toArray('dbiWrite')), false);
        } elseif (!is_int($value) && (':' !== substr($value, 0, 1) || ':' === substr($value, 1, 1))) {
            $value = $this->quote((string) $value);
        }

        return $value;
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
        ?string $bindType = null,
        ?string $tissue = null,
        ?string $parentRef = null,
        null|int|string $optionalKey = null
    ): string {
        if (!is_array($criteria)) {
            return $criteria;
        }
        $parts = [];
        if (0 === count($criteria)) {
            return 'TRUE';
        }
        if (null === $bindType) {
            $bindType = 'AND';
        }
        if (null === $tissue) {
            $tissue = '=';
        }
        foreach ($criteria as $key => $value) {
            $prepareValue = true;
            if ($value instanceof Table) {
                $value = '('.$value->toString().' )';
                $prepareValue = false;
            }
            if (is_int($key) && is_string($value)) {
                $parts[] = '('.$value.')';
            } elseif (!is_int($key) && '$' == substr($key, 0, 1)) {
                if ($actionParts = $this->prepareCriteriaAction(strtolower(substr($key, 1)), $value, $tissue, $optionalKey)) {
                    if (is_array($actionParts)) {
                        $parts = array_merge($parts, $actionParts);
                    } else {
                        $parts[] = $actionParts;
                    }
                } else {
                    $parts[] = ' '.$tissue.' '.$this->prepareCriteria($value, strtoupper(substr($key, 1)));
                }
            } elseif (is_array($value)) {
                $subValue = $this->prepareCriteria($value, $bindType, $tissue, $parentRef, $key);
                if (is_numeric($key)) {
                    $parts[] = $subValue;
                } else {
                    if ($parentRef && false === strpos($key, '.')) {
                        $key = $parentRef.'.'.$key;
                    }
                    $parts[] = $this->field($key).' '.$tissue.' '.$subValue;
                }
            } else {
                if ($parentRef && false === strpos($key, '.')) {
                    $key = $parentRef.'.'.$key;
                }
                if (is_null($value) || is_boolean($value)) {
                    $joiner = 'IS'.(('!=' === $tissue) ? 'NOT' : null);
                } else {
                    $joiner = $tissue;
                }
                $parts[] = $this->field($key).' '.$joiner.' '.($prepareValue ? $this->prepareValue($value) : $value);
            }
        }
        $sql = '';
        // @phpstan-ignore-next-line
        if (count($parts) > 0) {
            $sql = ((count($parts) > 1) ? '(' : null).implode(" {$bindType} ", $parts).((count($parts) > 1) ? ')' : null);
        }

        return $sql;
    }

    /**
     * @return null|array<string>|string
     */
    private function prepareCriteriaAction(
        string $action,
        mixed $value,
        string $tissue = '=',
        ?string $key = null,
        bool &$setKey = true
    ): null|array|string {
        switch ($action) {
            case 'and':
                return $this->prepareCriteria($value, 'AND');

            case 'or':
                return $this->prepareCriteria($value, 'OR');

            case 'ne':
                if (is_null($value)) {
                    return 'IS NOT NULL';
                }

                return (is_bool($value) ? 'IS NOT ' : '!= ').$this->prepareValue($value);

            case 'not':
                return 'NOT ('.$this->prepareCriteria($value).')';

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
                        $values[] = $this->prepareValue($val);
                    }
                    $value = implode(', ', $values);
                }

                return (('nin' == $action) ? 'NOT ' : null).'IN ('.$value.')';

            case 'gt':
                return '> '.$this->prepareValue($value);

            case 'gte':
                return '>= '.$this->prepareValue($value);

            case 'lt':
                return '< '.$this->prepareValue($value);

            case 'lte':
                return '<= '.$this->prepareValue($value);

            case 'ilike': // iLike
                return 'ILIKE '.$this->quote($value);

            case 'like': // Like
                return 'LIKE '.$this->quote($value);

            case 'bt':
                if (($count = count($value)) !== 2) {
                    throw new \Exception('DBD: $bt operator requires array argument with exactly 2 elements. '.$count.' given.');
                }

                return 'BETWEEN '.$this->prepareValue(array_values($value)[0])
                    .' AND '.$this->prepareValue(array_values($value)[1]);

            case '~':
            case '~*':
            case '!~':
            case '!~*':
                return $action.' '.$this->quote($value);

            case 'exists': // exists
                $parts = [];
                foreach ($value as $table => $criteria) {
                    $parts[] = 'EXISTS ( SELECT * FROM '.$table.' WHERE '.$this->prepareCriteria($criteria).' )';
                }

                return $parts;

            case 'sub': // sub query
                return '('.$value[0]->toString(false).') '.$this->prepareCriteria($value[1]);

            case 'json':
                return $this->prepareValue(json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return null;
    }

    /**
     * @return array<string>
     */
    private function tables(): array
    {
        if (!$this->from) {
            return [];
        }
        $tables = [$this->from[1] ?? $this->from[0] => $this->from[0]];
        foreach ($this->joins as $alias => $join) {
            $tables[$alias] = $join['ref'];
        }

        return $tables;
    }

    private function prepareFrom(): string
    {
        $tables = $this->from ? array_merge([$this->from[1] ?? $this->from[0] => $this->from[0]], $this->tables) : $this->tables;
        foreach ($tables as $alias => &$table) {
            if ($table[0] instanceof Table) {
                $schemaTable = '('.$table.')';
                if (!(is_string($alias) && $alias)) {
                    $alias = '_'.uniqid().'_';
                }
            } elseif (false === strpos($table, '(')) {
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
                $name = ake($this->selectGroups, $this->from[0], $this->from[0]).'.'.$field;
                if ($key = array_search($name, $this->selectGroups)) {
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
