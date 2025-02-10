<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Sync;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Sync\Enums\RowStatus;
use Hazaar\Model;

class Item extends Model
{
    public string $message;
    public string $exec;
    public string $table;
    public bool $truncate = false;
    public bool $insertOnly = false;

    /**
     * @var array<mixed>
     */
    public array $rows;

    /**
     * @var array<string>
     */
    public array $keys;

    /**
     * @var array<string,mixed>
     */
    public array $vars;

    /**
     * @var array<string,mixed>
     */
    public array $refs;

    /**
     * @var array{name: string, table: string, column: string, type: string}
     */
    private array $primaryKey;

    public function run(Adapter $dbi, Stats $stats): ?Stats
    {
        // If a message is set, log it
        if (isset($this->message)) {
            $dbi->log($this->message);
        }
        // If an exec statement is set, execute it
        if (isset($this->exec)) {
            $dbi->log('Executing SQL: '.$this->exec);
            $affectedRows = $dbi->exec($this->exec);
            $dbi->log('Affected rows: '.$affectedRows);
        }
        // If no table is set, skip the import
        if (!isset($this->table)) {
            return null;
        }
        // Get the primary key of the table
        $primaryKey = $dbi->table($this->table)->getPrimaryKey();
        if (false === $primaryKey) {
            $dbi->log('WARNING: Table '.$this->table.' does not have a primary key.  Skipping import.');

            return null;
        }
        $this->primaryKey = $primaryKey;
        // If the truncate flag is set, truncate the table
        if (true === $this->truncate) {
            $dbi->table($this->table)->truncate();
            $dbi->log('Truncated table '.$this->table);
        }
        // Apply variables to the sync refs
        if (isset($this->refs)) {
            $this->applyMacros($dbi, $this->refs); // Only apply macros to the refs as this will also apply the vars
        }
        $dbi->log('Processing '.count($this->rows)." rows in table '{$this->table}'");
        // Process each row
        foreach ($this->rows as $row) {
            // Apply macros to the row.  This will also apply the vars
            $this->applyMacros($dbi, $row);
            // If refs are set, merge them with the row
            if (isset($this->refs)) {
                $row = array_merge($row, $this->refs);
            }
            // Get the row status and add it to the stats
            $rowStatus = $stats->addRow($this->getRowItem($dbi, $row));
            if (RowStatus::UNCHANGED === $rowStatus) {
                continue;
            }

            // Perform the action based on the row status
            switch ($rowStatus) {
                case RowStatus::NEW:
                    $pkData = $dbi->insert($this->table, $row, $this->primaryKey['column']);
                    $dbi->log("Inserted row into table '{$this->table}' with key ".$this->primaryKey['column'].'='.$pkData);

                    break;

                case RowStatus::UPDATED:
                    if (true === $this->insertOnly) {
                        $dbi->log("Skipping update of row in table '{$this->table}' with key ".$this->primaryKey['column'].'='.$row[$this->primaryKey['column']]);

                        break;
                    }

                    $dbi->table($this->table)->update($row, [$this->primaryKey['column'] => $row[$this->primaryKey['column']]]);
                    $dbi->log("Updated row in table '{$this->table}' with key ".$this->primaryKey['column'].'='.$row[$this->primaryKey['column']]);

                    break;
            }
        }

        return $stats;
    }

    /**
     * @param array<mixed> $data
     */
    private function getRowItem(Adapter $dbi, array &$data): RowStatus
    {
        $criteria = [];
        if (isset($this->keys)) {
            foreach ($this->keys as $key) {
                $criteria[$key] = $data[$key];
            }
        } elseif (array_key_exists($this->primaryKey['column'], $data)) {
            $criteria[$this->primaryKey['column']] = $data[$this->primaryKey['column']];
        } else {
            $criteria = $data;
        }
        $existingRow = $dbi->table($this->table)->findOne($criteria);
        if (false === $existingRow) {
            return RowStatus::NEW;
        }
        $this->fixTypes($data, $existingRow);
        $diff = array_diff_assoc($data, $existingRow);
        if (0 === count($diff)) {
            return RowStatus::UNCHANGED;
        }
        $data[$this->primaryKey['column']] = $existingRow[$this->primaryKey['column']];

        return RowStatus::UPDATED;
    }

    /**
     * @param array<mixed> $row
     * @param array<mixed> $existingRow
     */
    private function fixTypes(array &$row, array &$existingRow): void
    {
        foreach ($row as $key => &$value) {
            if (!array_key_exists($key, $existingRow)) {
                continue;
            }
            if (is_int($existingRow[$key])) {
                $value = (int) $value;
            } elseif (is_float($existingRow[$key])) {
                $value = (float) $value;
            } elseif (is_bool($existingRow[$key])) {
                $value = boolify($value);
            } elseif (is_string($value)) {
                $macro = Macro::match($value);
                if (null !== $macro) {
                    $value = $macro;
                }
            }
        }
    }

    /**
     * @param array<mixed> $row
     */
    private function applyVars(array &$row): void
    {
        if (!isset($this->vars)) {
            return;
        }
        foreach ($row as &$field) {
            if (!is_string($field)) {
                continue;
            }
            $field = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) {
                if (isset($this->vars[$matches[1]])) {
                    return $this->vars[$matches[1]];
                }
            }, $field);
        }
    }

    /**
     * @param array<mixed> $row
     */
    private function applyMacros(Adapter $dbi, array &$row): void
    {
        $this->applyVars($row);
        foreach ($row as &$field) {
            if (!is_string($field)) {
                continue;
            }
            $macro = Macro::match($field);
            if (null === $macro) {
                continue;
            }
            $field = $macro->run($dbi);
        }
    }
}
