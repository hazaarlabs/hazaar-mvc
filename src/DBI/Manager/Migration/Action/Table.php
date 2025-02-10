<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Action\Component\Column;

class Table extends BaseAction
{
    public string $name;

    /**
     * @var array<Column>
     */
    public array $columns;

    /**
     * @var array<Column>
     */
    public array $add;

    /**
     * @var array<Column>
     */
    public array $alter;

    public function create(Adapter $dbi): bool
    {
        if (!isset($this->name)) {
            $dbi->log('ERROR: Table name not set for create action');

            return false;
        }
        if (!isset($this->columns) || 0 === count($this->columns)) {
            $dbi->log("ERROR: No columns defined for table '{$this->name}'");

            return false;
        }
        $columns = array_map(function (Column $column) {
            return $column->toArray();
        }, $this->columns);

        return $dbi->createTable($this->name, $columns);
    }

    public function alter(Adapter $dbi): bool
    {
        if (!isset($this->name)) {
            $dbi->log('ERROR: Table name not set for alter action');

            return false;
        }
        if (isset($this->add) && count($this->add) > 0) {
            foreach ($this->add as $column) {
                $result = $dbi->addColumn($this->name, $column->toArray());
                if (!$result) {
                    return false;
                }
            }
        }
        if (isset($this->drop) && count($this->drop) > 0) {
            foreach ($this->drop as $column) {
                $result = $dbi->dropColumn($this->name, $column, true);
                if (!$result) {
                    return false;
                }
            }
        }

        return true;
    }

    public function drop(Adapter $dbi): bool
    {
        if (!isset($this->drop)) {
            return false;
        }
        foreach ($this->drop as $table) {
            if (!$dbi->dropTable($table, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Applies the given action to the table.
     *
     * This method processes the action by adding, altering, or dropping columns
     * based on the properties of the provided BaseAction object.
     *
     * @param BaseAction $action The action to be applied. It may contain 'add', 'alter', and 'drop' properties.
     *                           - 'add': An array of columns to be added.
     *                           - 'alter': An array of columns to be altered.
     *                           - 'drop': An array of column names to be dropped.
     *
     * @return bool always returns false
     */
    public function apply(BaseAction $action): bool
    {
        if (!$action instanceof self) {
            return false;
        }
        if (isset($action->add) && count($action->add) > 0) {
            foreach ($action->add as $column) {
                $this->columns[] = $column;
            }
        }
        if (isset($action->alter) && count($action->alter) > 0) {
            foreach ($action->alter as $column) {
                $index = array_usearch($this->columns, function (Column $localColumn) use ($column) {
                    return $localColumn->name === $column->name;
                });
                if (false !== $index) {
                    $this->columns[$index] = $column;
                }
            }
        }
        if (isset($action->drop) && count($action->drop) > 0) {
            $this->columns = array_filter($this->columns, function (Column $column) use ($action) {
                return !in_array($column->name, $action->drop);
            });
        }

        return false;
    }

    public function diff(BaseAction $table): ?self
    {
        if (!$table instanceof self) {
            return null;
        }
        $diff = new self([
            'name' => $this->name,
        ]);
        foreach ($table->columns as $column) {
            $index = array_usearch($this->columns, function (Column $localColumn) use ($column) {
                return $localColumn->name === $column->name;
            });
            // Column does not exist in local schema. Add it. Otherwise, check if the column has changed.
            if (false === $index) {
                if (!isset($diff->add)) {
                    $diff->add = [];
                }
                $diff->add[] = $column;
            } elseif ($this->columns[$index]->changed($column)) {
                if (!isset($diff->alter)) {
                    $diff->alter = [];
                }
                $diff->alter[] = $column;
            }
        }
        foreach ($this->columns as $column) {
            $index = array_usearch($table->columns, function (Column $remoteColumn) use ($column) {
                return $remoteColumn->name === $column->name;
            });
            // Column does not exist in remote schema. Drop it.
            if (false === $index) {
                if (!isset($diff->drop)) {
                    $diff->drop = [];
                }
                $diff->drop[] = $column->name;
            }
        }
        if (isset($diff->add)
            || isset($diff->alter)
            || isset($diff->drop)) {
            return $diff;
        }

        return null;
    }

    public function reverse(self $sourceTable): self
    {
        $spec = [
            'name' => $this->name,
        ];
        if (isset($this->drop)) {
            $spec['add'] = [];
            foreach ($this->drop as $column) {
                $spec['add'][] = $sourceTable->columns[array_usearch($sourceTable->columns, function (Column $sourceColumn) use ($column) {
                    return $sourceColumn->name === $column;
                })];
            }
        }
        if (isset($this->add)) {
            $spec['drop'] = [];
            foreach ($this->add as $column) {
                $spec['drop'][] = $column->name;
            }
        }

        return new self($spec);
    }
}
