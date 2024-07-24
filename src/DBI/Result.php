<?php

declare(strict_types=1);

namespace Hazaar\DBI;

use Hazaar\Date;
use Hazaar\Map;

abstract class Result implements Interfaces\Result, \Countable
{
    /**
     * @var array<string, array<string>>
     */
    protected array $arrayColumns = [];

    /**
     * @var array<mixed>
     */
    protected array $meta;

    /**
     * @var array<int, Row>
     */
    private ?array $records = null;
    private ?Row $record = null;

    /**
     * @var bool indicates whether the object needs to be woken up from a serialized state
     */
    private bool $wakeup = false;

    /**
     * @var array<string>
     */
    private array $selectGroups = [];

    private mixed $encrypt = null;

    public function __toString(): string
    {
        return $this->toString();
    }

    // Countable
    public function count(): int
    {
        return $this->rowCount();
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

    /**
     * @return array<mixed>
     */
    public function all(): array
    {
        return $this->fetchAll();
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

    public function row(int $cursor_orientation = \PDO::FETCH_ORI_NEXT, int $offset = 0): ?Row
    {
        if ($record = $this->fetch(\PDO::FETCH_NAMED, $cursor_orientation, $offset)) {
            $this->decrypt($record);

            return new Row($record, $this->meta);
        }

        return null;
    }

    /**
     * @return array<Row>
     */
    public function rows(): ?array
    {
        if ($records = $this->fetchAll(\PDO::FETCH_NAMED)) {
            foreach ($records as &$record) {
                // $this->decrypt($record);
                $record = new Row($record, $this->meta);
            }

            return $records;
        }

        return null;
    }

    /**
     * @param array<mixed> $record
     */
    protected function fix(array &$record): void
    {
        if ((count($this->arrayColumns) + count($this->selectGroups)) > 0) {
            foreach ($this->arrayColumns as $col => $arrayColumns) {
                if (count($arrayColumns) > 1) {
                    $columns = &$record[$col];
                } else {
                    $columns = [&$record[$col]];
                }
                foreach ($arrayColumns as $index => $type) {
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
                if (array_key_exists($name, $this->selectGroups)) {
                    $objs[$this->selectGroups[$name]] = $value;
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
                    if (!array_key_exists($alias, $this->selectGroups)) {
                        continue;
                    }
                    while (array_key_exists($alias, $this->selectGroups) && $this->selectGroups[$alias] !== $alias) {
                        $alias = $this->selectGroups[$alias];
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

        // $this->decrypt($record);
    }

    /**
     * @param array<mixed> $data
     */
    protected function decrypt(array &$data): void
    {
        if (null === $this->encrypt
            || !(count($data) > 0)) {
            return;
        }
        $cipher = $this->encrypt->get('cipher');
        $key = $this->encrypt->get('key', '0000');
        $checkstring = $this->encrypt->get('checkstring');
        $encryptedFields = [];
        foreach ($data as $column => &$value) {
            if (!array_key_exists($column, $this->meta)) {
                continue;
            }
            $table = ake($this->meta[$column], 'table');
            if (!array_key_exists($table, $encryptedFields)) {
                $encryptedFields[$table] = ake($this->encrypt['table'], $table, []);
            }
            if ((!($encryptedFields[$table] instanceof Map && $encryptedFields[$table]->contains($column))
                && true !== $encryptedFields[$table])
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
            list($checkbit, $decryptedValue) = preg_split('/(?<=.{'.strlen($checkstring).'})/s', openssl_decrypt($parts[1], $cipher, $key, OPENSSL_RAW_DATA, $parts[0]), 2);
            if ($checkbit === $checkstring) {
                $value = $decryptedValue;
            }
        }
    }
}
