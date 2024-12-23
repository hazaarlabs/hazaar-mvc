<?php

declare(strict_types=1);

/**
 * @file        Hazaar/DBI/Statement/Model.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2019 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\DBI;

use Hazaar\Model;

if (!defined('HAZAAR_VERSION')) {
    exit('Hazaar\DBI\Row requires Hazaar to be installed!');
}
final class Row extends Model
{
    private Adapter $adapter;
    private ?\PDOStatement $statement = null;

    /**
     * @var array<string>
     */
    private array $changedProperties = [];

    /**
     * @var array<string,\stdClass>
     */
    private array $propertyMeta;

    /**
     * Prepare the row values by checking for fields that are an array that should not be.
     *
     * This will happen when a join selects multiple fields from different tables with the same name.  For example, when
     * doing a SELECT * with multiple tables that all have an 'id' column.  The 'id' columns from each table will clobber
     * the earlier value as each table is processed, meaning the Row::update() may not work.  To get around this, the
     * Row class is given data using the \PDO::FETCH_NAMED flag which will cause multiple columns with the same name to be
     * returned as an array.  However, the column will not have an array data type so we detect that and just grab the
     * first value in the array.
     *
     * @param array<mixed> $data
     */
    public function prepare(array &$data): void
    {
        // foreach ($this->fields as $key => $def) {
        //     if (!(array_key_exists($key, $data) && is_array($data[$key])) || 'array' === ake($def, 'type', 'none')) {
        //         if (is_string($data[$key])) {
        //             $data[$key] = trim($data[$key]);
        //         }

        //         continue;
        //     }
        //     $data[$key] = array_shift($data[$key]);
        // }
    }

    /**
     * Update the database with any changes to the current row, optionally providing updates directly.
     *
     * @param array<mixed> $data Column updates that will be applied to the object directly
     */
    public function update(?array $data = null): bool
    {
        if (!$this->statement instanceof \PDOStatement) {
            throw new \Exception('Unable to perform updates without the original PDO statement!');
        }
        $schemaName = $this->adapter->getSchemaName();
        $changes = [];
        foreach ($this->changedProperties as $propertyName) {
            if (false === array_key_exists($propertyName, $this->propertyMeta)) {
                continue;
            }
            $propertyMeta = $this->propertyMeta[$propertyName];
            if (!property_exists($propertyMeta, 'table')) {
                throw new \Exception('Unable to update '.$propertyName.' with unknown table');
            }
            $changes[ake($propertyMeta, 'schema', $schemaName).'.'.$propertyMeta['table']] = $this->get($propertyName);
        }
        // Check if there are changes and if not, bomb out now as there's no point continuing.
        if (count($changes) <= 0) {
            return false;
        }
        // Defined keyword boundaries.  These are used to detect the end of things like table names if they have no alias.
        $keywords = [
            'FROM',
            'INNER',
            'LEFT',
            'OUTER',
            'JOIN',
            'WHERE',
            'GROUP',
            'HAVING',
            'WINDOW',
            'UNION',
            'INTERSECT',
            'EXCEPT',
            'ORDER',
            'LIMIT',
            'OFFSET',
            'FETCH',
            'FOR',
        ];
        $tables = [];
        if (!preg_match('/FROM\s+"?(\w+)"?(\."?(\w+)")?(\s+AS)?(\s+"?(\w+)"?)?/', $this->statement->queryString, $matches)) {
            throw new \Exception('Can\'t figure out which table we\'re updating!');
        }
        $tableName = $matches[3] ? $matches[1].'.'.$matches[3] : $matches[1];
        // Find the primary key for the primary table so we know which row we are updating
        foreach ($this->adapter->listPrimaryKeys($tableName) as $data) {
            if (!$this->has($data['column'])) {
                continue;
            }
            $tables[$tableName] = ['primary' => true];
            if (isset($matches[6]) && !in_array(strtoupper($matches[6]), $keywords)) {
                $tables[$tableName]['alias'] = $matches[6];
            }
            $tables[$tableName]['condition'] = ake($tables[$tableName], 'alias', $tableName).'.'.$data['column'].'='.$this->get($data['column']);

            break;
        }
        if (!count($tables) > 0) {
            throw new \Exception('Missing primary key in selection!');
        }
        // Check and process joins
        if (preg_match_all('/JOIN\s+"?(\w+)"?(\s"?(\w+)"?)?\s+ON\s+("?[\w\.]+"?)\s?([\!=<>])\s?("?[\w\.]+"?)/i', $this->statement->queryString, $matches)) {
            foreach ($matches[0] as $idx => $match) {
                $tables[$matches[1][$idx]] = [
                    'condition' => $matches[4][$idx].$matches[5][$idx].$matches[6][$idx],
                ];
                if ($matches[3][$idx]) {
                    $tables[$matches[1][$idx]]['alias'] = $matches[3][$idx];
                }
            }
        }
        $committedUpdates = [];
        $this->adapter->beginTransaction();
        foreach ($changes as $table => $updates) {
            $conditions = [];
            $from = [];
            if (true === ake($tables[$table], 'primary')) {
                $conditions[] = ake($tables[$table], 'condition');
            } elseif ($tables[$table]['condition']) {
                foreach ($tables as $fromTable => $data) {
                    $conditions[] = $data['condition'];
                    if ($table !== $fromTable) {
                        $from[] = $fromTable.(array_key_exists('alias', $data) ? ' '.$data['alias'] : null);
                    }
                }
            }
            $tableName = $table.(array_key_exists('alias', $tables[$table]) ? ' '.$tables[$table]['alias'] : '');
            if (!($committedUpdates[$table] = $this->adapter->update($tableName, $updates, $conditions, $from, array_keys($updates)))) {
                $this->adapter->rollback();

                return false;
            }
        }
        if (count($committedUpdates) === count($changes)) {
            $this->adapter->commit();
            foreach ($committedUpdates as $table => $updates) {
                foreach ($updates as $update) {
                    foreach ($update as $key => $value) {
                        $this->set($key, $update[$key]);
                    }
                }
            }

            return true;
        }
        $this->adapter->rollback();

        return false;
    }

    /**
     * Row constructor.
     *
     * @param array<string,\stdClass> $meta
     */
    protected function construct(
        array &$data,
        ?Adapter $adapter = null,
        array $meta = [],
        ?\PDOStatement $statement = null
    ): void {
        $this->adapter = $adapter;
        $this->propertyMeta = $meta;
        $this->statement = $statement;
        foreach ($meta as $propertyName => $propertyMeta) {
            $this->defineProperty($propertyMeta->type, $propertyName);
        }
    }

    protected function constructed(array &$data): void
    {
        $this->defineEventHook('written', function ($propertyValue, $propertyName) {
            $this->changedProperties[] = $propertyName;
        });
    }
}
