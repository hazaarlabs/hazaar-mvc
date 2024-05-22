<?php

declare(strict_types=1);

/**
 * @file        Hazaar/DBI/Result.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
/**
 * @brief Relational database namespace
 */

namespace Hazaar\DBI;

use Hazaar\Date;
use Hazaar\Map;

/**
 * @brief Relational Database Interface - Result Class
 *
 * @implements \Iterator<int, Row>
 */
class Result implements \Countable, \Iterator
{
    private \PDOStatement $statement;
    private ?Row $record = null;

    /**
     * @var array<int, Row>
     */
    private ?array $records = null;

    /**
     * @var array<string, array<string>>
     */
    private array $array_columns = [];

    /**
     * @var array<string,mixed>
     */
    private array $type_map = [
        'numeric' => 'integer',
        'int2' => 'integer',
        'int4' => 'integer',
        'int8' => 'integer',
        'float8' => 'float',
        'timestamp' => '\Hazaar\Date',
        'timestamptz' => '\Hazaar\Date',
        'date' => ['\Hazaar\Date', ['format' => 'Y-m-d']],
        'bool' => 'boolean',
        'money' => '\Hazaar\Money',
        'bytea' => ['string', ['prepare' => 'stream_get_contents']],
    ];

    /**
     * @var array<mixed>
     */
    private array $meta;
    private Adapter $adapter;

    private ?Map $encrypt = null;

    /**
     * Flag to remember if we need to reset the statement when using array access methods.
     * A reset is required once an 'execute' is made and then rows are accessed. If no rows
     * are accessed then a reset is not required. This prevents a query from being executed
     * multiple times when it's not necessary.
     */
    private bool $reset = true;

    /**
     * @var bool indicates whether the object needs to be woken up from a serialized state
     */
    private bool $wakeup = false;

    /**
     * @var array<string>
     */
    private array $select_groups = [];
    private int $fetch_mode = \PDO::FETCH_ASSOC;

    public function __construct(Adapter $adapter, \PDOStatement $statement)
    {
        $this->adapter = $adapter;
        $this->statement = $statement;
        $this->encrypt = $adapter->config->get('encrypt');
        $this->processStatement($statement);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function __get(string $key): mixed
    {
        if (!$this->record) {
            $this->reset = true;
            $this->next();
        }

        return $this->record->get($key);
    }

    public function __sleep()
    {
        $this->store();

        return ['records'];
    }

    public function __wakeup()
    {
        $this->wakeup = true;
    }

    /**
     * @param array<string> $select_groups
     */
    public function setSelectGroups(array $select_groups): void
    {
        if (\is_array($select_groups)) {
            $this->select_groups = $select_groups;
        }
    }

    public function hasSelectGroups(): bool
    {
        return count($this->select_groups) > 0;
    }

    public function toString(): string
    {
        return $this->statement->queryString;
    }

    public function bindColumn(
        int|string $column,
        mixed &$param,
        ?int $type = null,
        ?int $maxlen = null,
        mixed $driverdata = null
    ): bool {
        if (null !== $driverdata) {
            return $this->statement->bindColumn($column, $param, $type, $maxlen, $driverdata);
        }
        if (null !== $maxlen) {
            return $this->statement->bindColumn($column, $param, $type, $maxlen);
        }
        if (null !== $type) {
            return $this->statement->bindColumn($column, $param, $type);
        }

        return $this->statement->bindColumn($column, $param);
    }

    public function bindParam(
        int|string $parameter,
        mixed &$variable,
        int $data_type = \PDO::PARAM_STR,
        ?int $length = null,
        mixed $driver_options = null
    ): bool {
        if (null !== $driver_options) {
            return $this->statement->bindParam($parameter, $variable, $data_type, $length, $driver_options);
        }
        if (null !== $length) {
            return $this->statement->bindParam($parameter, $variable, $data_type, $length);
        }

        return $this->statement->bindParam($parameter, $variable, $data_type);
    }

    public function bindValue(int|string $parameter, mixed $value, int $data_type = \PDO::PARAM_STR): bool
    {
        return $this->statement->bindValue($parameter, $value, $data_type);
    }

    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    public function debugDumpParams(): void
    {
        $this->statement->debugDumpParams();
    }

    public function errorCode(): string
    {
        return $this->statement->errorCode();
    }

    /**
     * @return array<int,string>
     */
    public function errorInfo(): array
    {
        return $this->statement->errorInfo();
    }

    /**
     * @param array<string> $input_parameters
     */
    public function execute(array $input_parameters = []): bool
    {
        $this->reset = false;
        if (count($input_parameters) > 0) {
            $result = $this->statement->execute($input_parameters);
        } else {
            $result = $this->statement->execute();
        }
        if (!$result) {
            return false;
        }
        $this->processStatement($this->statement);
        if (preg_match('/^INSERT/i', $this->statement->queryString)) {
            return $this->adapter->lastInsertId();
        }

        return $result;
    }

    /**
     * @return null|array<mixed>
     */
    public function fetch(
        ?int $fetch_style = null,
        int $cursor_orientation = \PDO::FETCH_ORI_NEXT,
        int $cursor_offset = 0
    ): ?array {
        if (null === $fetch_style) {
            $fetch_style = $this->fetch_mode;
        }
        $this->reset = true;
        if ($record = $this->statement->fetch($fetch_style, $cursor_orientation, $cursor_offset)) {
            $this->fix($record);

            return $record;
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    public function fetchAll(
        ?int $fetch_mode = null,
        mixed $fetch_argument = null
    ): array {
        if (null === $fetch_mode) {
            $fetch_mode = $this->fetch_mode;
        }
        $this->reset = true;
        if (null !== $fetch_argument) {
            $results = $this->statement->fetchAll($fetch_mode, $fetch_argument);
        } else {
            $results = $this->statement->fetchAll($fetch_mode);
        }
        foreach ($results as &$record) {
            $this->fix($record);
        }

        return $results;
    }

    public function fetchColumn(int $column_number = 0): mixed
    {
        $this->reset = true;

        return $this->statement->fetchColumn($column_number);
    }

    /**
     * @param array<mixed> $constructorArgs
     */
    public function fetchObject(string $class_name = 'stdClass', array $constructorArgs = []): false|object
    {
        $this->reset = true;

        return $this->statement->fetchObject($class_name, $constructorArgs);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->statement->getAttribute($attribute);
    }

    public function getColumnMeta(?string $column = null): mixed
    {
        return $column ? ake($this->meta, $column) : $this->meta;
    }

    public function nextRowset(): bool
    {
        $this->reset = true;

        return $this->statement->nextRowset();
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    // Countable
    public function count(): int
    {
        return $this->rowCount();
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->statement->setAttribute($attribute, $value);
    }

    public function setFetchMode(int $mode): bool
    {
        return $this->statement->setFetchMode($this->fetch_mode = $mode);
    }

    /**
     * @return array<mixed>
     */
    public function all(): array
    {
        return $this->fetchAll();
    }

    public function row(int $cursor_orientation = \PDO::FETCH_ORI_NEXT, int $offset = 0): ?Row
    {
        $this->reset = true;
        if ($record = $this->statement->fetch(\PDO::FETCH_NAMED, $cursor_orientation, $offset)) {
            $this->decrypt($record);

            return new Row($record, $this->adapter, $this->meta, $this->statement);
        }

        return null;
    }

    /**
     * @return array<Row>
     */
    public function rows(): ?array
    {
        $this->reset = true;
        if ($records = $this->statement->fetchAll(\PDO::FETCH_NAMED)) {
            foreach ($records as &$record) {
                $this->decrypt($record);
                $record = new Row($record, $this->adapter, $this->meta, $this->statement);
            }

            return $records;
        }

        return null;
    }

    public function current(): Row
    {
        if ($this->wakeup) {
            return current($this->records);
        }

        return $this->record;
    }

    public function key(): null|int|string
    {
        return key($this->records);
    }

    public function next(): void
    {
        if ($this->wakeup) {
            next($this->records);

            return;
        }
        $this->record = null;
        $this->reset = true;
        if ($record = $this->statement->fetch(\PDO::FETCH_NAMED, \PDO::FETCH_ORI_NEXT)) {
            $this->decrypt($record);
            $this->record = new Row($record, $this->adapter, $this->meta, $this->statement);
        }
    }

    public function rewind(): void
    {
        if ($this->wakeup) {
            reset($this->records);

            return;
        }
        $this->record = null;
        if (true === $this->reset) {
            $this->statement->execute();
        }
        $this->reset = false;
        if ($record = $this->statement->fetch(\PDO::FETCH_NAMED, \PDO::FETCH_ORI_NEXT)) {
            $this->decrypt($record);
            $this->record = new Row($record, $this->adapter, $this->meta, $this->statement);
        }
    }

    public function valid(): bool
    {
        if ($this->wakeup) {
            return current($this->records);
        }

        return $this->record instanceof Row;
    }

    /**
     * Collates a result into a simple key/value array.
     *
     * This is useful for generating SELECT lists directly from a resultset.
     *
     * @param int|string $index_column the column to use as the array index
     * @param int|string $value_column the column to use as the array value
     * @param int|string $group_column optional column name to group items by
     *
     * @return array<mixed>
     */
    public function collate(int|string $index_column, int|string $value_column, null|int|string $group_column = null): array
    {
        return array_collate($this->fetchAll(), $index_column, $value_column, $group_column);
    }

    private function processStatement(\PDOStatement $statement): bool
    {
        // @var array<mixed>
        $this->meta = [];
        for ($i = 0; $i < $this->statement->columnCount(); ++$i) {
            $meta = $this->statement->getColumnMeta($i);
            $def = ['native_type' => $meta['native_type']];
            if (array_key_exists('table', $meta)) {
                $def['table'] = $meta['table'];
            }
            if ('_' == substr($meta['native_type'], 0, 1)) {
                if (!array_key_exists($meta['name'], $this->array_columns)) {
                    $this->array_columns[$meta['name']] = [];
                }
                $this->array_columns[$meta['name']][] = substr($meta['native_type'], 1);
                $type = substr($meta['native_type'], 1);
                $def['type'] = 'array';
                $def['arrayOf'] = ake($this->type_map, $type, 'string');
                $def['prepare'] = function ($value) {
                    if (is_string($value)) {
                        return explode(',', trim($value, '{}'));
                    }

                    return $value;
                };
            } elseif (\PDO::PARAM_STR == $meta['pdo_type'] && ('json' == substr(ake($meta, 'native_type'), 0, 4)
                    || (!array_key_exists('native_type', $meta) && in_array('blob', ake($meta, 'flags'))))) {
                if (!array_key_exists($meta['name'], $this->array_columns)) {
                    $this->array_columns[$meta['name']] = [];
                }

                $this->array_columns[$meta['name']][] = 'json';
                $def['prepare'] = function ($value) {
                    if (is_string($value)) {
                        return json_decode($value);
                    }

                    return $value;
                };
            } elseif ('record' === $meta['native_type']) {
                if (!array_key_exists($meta['name'], $this->array_columns)) {
                    $this->array_columns[$meta['name']] = [];
                }

                $this->array_columns[$meta['name']][] = 'record';
            } else {
                $type_map = ake($this->type_map, $meta['native_type'], 'string');
                $extra = is_array($type_map) ? $type_map[1] : [];
                $type_map = is_array($type_map) ? $type_map[0] : $type_map;
                $def = array_merge($def, ['type' => $type_map], $extra);
            }
            if (array_key_exists($meta['name'], $this->meta)) {
                if (false === is_array($this->meta[$meta['name']])) {
                    $this->meta[$meta['name']] = [$this->meta[$meta['name']]];
                }
                $this->meta[$meta['name']][] = (object) $def;
            } else {
                $this->meta[$meta['name']] = (object) $def;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $record
     */
    private function fix(array &$record): void
    {
        if ((count($this->array_columns) + count($this->select_groups)) > 0) {
            foreach ($this->array_columns as $col => $array_columns) {
                if (count($array_columns) > 1) {
                    $columns = &$record[$col];
                } else {
                    $columns = [&$record[$col]];
                }
                foreach ($array_columns as $index => $type) {
                    if (null === $columns[$index]) {
                        continue;
                    }
                    if ('json' == $type) {
                        $columns[$index] = json_decode($columns[$index]);

                        continue;
                    }
                    if ('record' === $type) {
                        $columns[$index] = str_getcsv(trim($columns[$index], '()'));

                        continue;
                    }
                    if (!($columns[$index] && '{' == substr($columns[$index], 0, 1) && '}' == substr($columns[$index], -1, 1))) {
                        continue;
                    }
                    $elements = explode(',', trim($columns[$index], '{}'));
                    foreach ($elements as &$element) {
                        if ('int' == substr($type, 0, 3)) {
                            $element = (int) $element;
                        } elseif ('float' == substr($type, 0, 5)) {
                            $element = floatval($element);
                        } elseif ('text' == $type || 'varchar' == $type) {
                            $element = trim($element, "'\"");
                        } elseif ('bool' == $type) {
                            $element = boolify($element);
                        } elseif ('timestamp' == $type || 'date' == $type || 'time' == $type) {
                            $element = new Date(trim($element, '"'));
                        } elseif ('json' == $type) {
                            $element = json_decode($element);
                        }
                    }
                    $columns[$index] = $elements;
                }
                unset($columns);
            }
            $objs = [];
            foreach ($record as $name => $value) {
                if (array_key_exists($name, $this->select_groups)) {
                    $objs[$this->select_groups[$name]] = $value;
                    unset($record[$name]);

                    continue;
                }
                if (!array_key_exists($name, $this->meta)) {
                    continue;
                }
                $aliases = [];
                // @var array<int>
                $meta = null;
                if (is_array($this->meta[$name])) {
                    $meta = [];
                    foreach ($this->meta[$name] as $col) {
                        $meta[] = $col;
                        $aliases[] = ake($col, 'table');
                    }
                } else {
                    $meta = $this->meta[$name];
                    if (!($alias = ake($meta, 'table'))) {
                        continue;
                    }
                    $aliases[] = $alias;
                }
                foreach ($aliases as $idx => $alias) {
                    if (!array_key_exists($alias, $this->select_groups)) {
                        continue;
                    }
                    while (array_key_exists($alias, $this->select_groups) && $this->select_groups[$alias] !== $alias) {
                        $alias = $this->select_groups[$alias];
                    }
                    if (!isset($objs[$alias])) {
                        $objs[$alias] = [];
                    }
                    // @phpstan-ignore-next-line
                    $objs[$alias][$name] = (is_array($value) && is_array($meta)) ? $value[$idx] : $value;
                    unset($record[$name]);
                }
            }
            $record = array_merge($record, array_from_dot_notation($objs));
        }

        $this->decrypt($record);
    }

    private function store(): void
    {
        $this->records = $this->statement->fetchAll($this->fetch_mode);
        foreach ($this->records as &$record) {
            $this->decrypt($record);
            new Row($record, $this->adapter, $this->meta, $this->statement);
        }
        $this->wakeup = true;
        $this->reset = true;
    }

    /**
     * @param array<mixed> $data
     */
    private function decrypt(array &$data): void
    {
        if (null === $this->encrypt
            || !(count($data) > 0)) {
            return;
        }
        $cipher = $this->encrypt->get('cipher');
        $key = $this->encrypt->get('key', '0000');
        $checkstring = $this->encrypt->get('checkstring');
        $encrypted_fields = [];
        foreach ($data as $column => &$value) {
            if (!array_key_exists($column, $this->meta)) {
                continue;
            }
            $table = ake($this->meta[$column], 'table');
            if (!array_key_exists($table, $encrypted_fields)) {
                $encrypted_fields[$table] = ake($this->encrypt['table'], $table, []);
            }
            if ((!($encrypted_fields[$table] instanceof Map && $encrypted_fields[$table]->contains($column))
                && true !== $encrypted_fields[$table])
                || 'string' !== ake($this->meta[$column], 'type')) {
                continue;
            }
            if (null === $value) {
                continue;
            }
            $parts = preg_split('/(?<=.{'.openssl_cipher_iv_length($cipher).'})/s', base64_decode($value), 2);
            if (2 !== count($parts)) {
                continue;
            }
            list($checkbit, $decrypted_value) = preg_split('/(?<=.{'.strlen($checkstring).'})/s', openssl_decrypt($parts[1], $cipher, $key, OPENSSL_RAW_DATA, $parts[0]), 2);
            if ($checkbit === $checkstring) {
                $value = $decrypted_value;
            }
        }
    }
}
