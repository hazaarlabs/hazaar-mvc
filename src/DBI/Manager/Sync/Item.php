<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Sync;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Sync\Enums\RowStatus;
use Hazaar\Model;

class Item extends Model
{
    public string $message;
    public string $table;
    public bool $truncate = false;

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

    private mixed $primaryKey;

    public function run(Adapter $dbi, Stats $stats): ?Stats
    {
        if (isset($this->message)) {
            $dbi->log($this->message);
        }
        if (!isset($this->table)) {
            return null;
        }
        $this->primaryKey = $dbi->table($this->table)->getPrimaryKey();
        if (!isset($this->primaryKey)) {
            $dbi->log('Table '.$this->table.' does not have a primary key.  Skipping import.');

            return null;
        }
        if ($this->truncate) {
            $dbi->table($this->table)->truncate();
            $dbi->log('Truncated table '.$this->table);
        }
        $this->applyVars($this->refs);
        $dbi->log('Processing '.count($this->rows)." rows in table '{$this->table}'");
        foreach ($this->rows as $row) {
            $this->applyVars($row);
            if (isset($this->refs)) {
                $row = array_merge($row, $this->refs);
            }
            $rowStatus = $stats->addRow($this->getRowItem($dbi, $row));
            if (RowStatus::UNCHANGED === $rowStatus) {
                continue;
            }

            switch ($rowStatus) {
                case RowStatus::NEW:
                    $pkData = $dbi->insert($this->table, $row, $this->primaryKey['column']);
                    $dbi->log("Inserted row into table '{$this->table}' with key ".$this->primaryKey['column'].'='.$pkData);

                    break;

                case RowStatus::UPDATED:
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
            $field = preg_replace_callback('/\{\{([a-zA-Z0-9_]+)\}\}/', function ($matches) {
                if (isset($this->vars[$matches[1]])) {
                    return $this->vars[$matches[1]];
                }
            }, $field);
        }
    }
}
