<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD\Traits;

use Hazaar\Date;
use Hazaar\DBI2\Table;

trait SQL
{
    /**
     * @var array<string>
     */
    protected array $reservedWords = [];
    protected string $quoteSpecial = '"';
    private string $method = 'SELECT';

    /**
     * @var array<string>|string
     */
    private array|string $columns = [];
    private string $table = '';

    /**
     * @var array<string>|string
     */
    private array|string $where = [];
    private ?int $limit = null;
    private ?int $offset = null;

    private ?string $returning = null;

    /**
     * @var array<string>
     */
    private array $selectGroups = [];

    public function select(array|string $columns = '*'): self
    {
        $this->method = 'SELECT';
        $this->columns = $columns;

        return $this;
    }

    public function table(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function where(array|string $where): self
    {
        $this->where = $where;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function insert(array $values, ?string $returning = null): self
    {
        $this->method = 'INSERT';
        $this->columns = $values;
        $this->returning = $returning;

        return $this;
    }

    public function toString(): string
    {
        $sql = $this->method;

        switch ($this->method) {
            case 'SELECT':
                if (!empty($this->columns)) {
                    $sql .= ' '.$this->prepareFields($this->columns);
                }
                if (!empty($this->table)) {
                    $sql .= ' FROM '.$this->table;
                }
                if (!empty($this->where)) {
                    $sql .= ' WHERE '.$this->prepareCriteria($this->where);
                }
                if (!empty($this->limit)) {
                    $sql .= ' LIMIT '.$this->limit;
                }
                if (!empty($this->offset)) {
                    $sql .= ' OFFSET '.$this->offset;
                }

                break;

            case 'INSERT':
                $sql .= ' INTO '.$this->table
                    .' ('.$this->prepareFields(array_keys($this->columns)).')'
                    .' VALUES ('.$this->prepareValues($this->columns).')';

                if ($this->returning) {
                    $sql .= ' RETURNING '.$this->returning;
                }
        }

        return $sql;
    }

    /**
     * @param array<mixed> $criteria
     */
    protected function prepareCriteria(
        array|string $criteria,
        ?string $bindType = null,
        ?string $tissue = null,
        ?string $parentRef = null,
        ?string $optionalKey = null,
        bool &$setKey = true
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
            if ($value instanceof Table) {
                $value = '('.$value->toString().' )';
            }
            if (is_int($key) && is_string($value)) {
                $parts[] = '('.$value.')';
            } elseif ('$' == substr($key, 0, 1)) {
                if ($actionParts = $this->prepareCriteriaAction(strtolower(substr($key, 1)), $value, $tissue, $optionalKey, $setKey)) {
                    if (is_array($actionParts)) {
                        $parts = array_merge($parts, $actionParts);
                    } else {
                        $parts[] = $actionParts;
                    }
                } else {
                    $parts[] = ' '.$tissue.' '.$this->prepareCriteria($value, strtoupper(substr($key, 1)));
                }
            } else {
                if (is_array($value)) {
                    $set = true;
                    $subValue = $this->prepareCriteria($value, $bindType, $tissue, $parentRef, $key, $set);
                    if (is_numeric($key)) {
                        $parts[] = $subValue;
                    } else {
                        if ($parentRef && false === strpos($key, '.')) {
                            $key = $parentRef.'.'.$key;
                        }
                        $parts[] = ((true === $set) ? $this->field($key).' ' : '').$subValue;
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
                    $parts[] = $this->field($key).' '.$joiner.' '.$this->prepareValue($value);
                }
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
    protected function prepareCriteriaAction(
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
     * @param array<string> $exclude
     * @param array<string> $tables
     */
    protected function prepareFields(mixed $fields, array $exclude = [], array $tables = []): string
    {
        if (!is_array($fields)) {
            return $this->field($fields);
        }
        if (!is_array($exclude)) {
            $exclude = [];
        }
        $fieldDef = [];
        foreach ($fields as $key => $value) {
            // if ($value instanceof Table) {
            //     $value = ((1 === $value->limit()) ? '(' : 'array(').$value.')';
            // }
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

    protected function prepareValues(mixed $values): string
    {
        if (!is_array($values)) {
            $values = [$values];
        }
        foreach ($values as &$value) {
            $value = $this->prepareValue($value);
        }

        return implode(',', $values);
    }

    protected function prepareValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value) && count($value) > 0) {
            $value = $this->prepareCriteria($value, null, null, null, $key);
        } elseif ($value instanceof Date) {
            $value = $this->quote($value->format('Y-m-d H:i:s'));
        } elseif (is_null($value) || (is_array($value) && 0 === count($value))) {
            $value = 'NULL';
        } elseif (is_bool($value)) {
            $value = ($value ? 'TRUE' : 'FALSE');
        } elseif ($value instanceof \stdClass) {
            $value = $this->quote(json_encode($value));
        } elseif (!is_int($value) && (':' !== substr($value, 0, 1) || ':' === substr($value, 1, 1))) {
            $value = $this->quote((string) $value);
        }

        return $value;
    }

    protected function field(string $string): string
    {
        if (in_array(strtoupper($string), $this->reservedWords)) {
            $string = $this->quoteSpecial($string);
        }

        return $string;
    }

    /**
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    protected function prepareArrayAliases(array $array): array
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
}
