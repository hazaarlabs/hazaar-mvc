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
     * @var array<string>
     */
    public array $drop;

    public function construct(array &$data): void
    {
        if (!array_key_exists('columns', $data)) {
            return;
        }
        foreach ($data['columns'] as &$column) {
            $column = new Column($column);
        }
    }

    public function create(Adapter $dbi): bool
    {
        $columns = array_map(function (Column $column) {
            return $column->toArray();
        }, $this->columns);

        return $dbi->createTable($this->name, $columns);
    }

    public function alter(Adapter $dbi): bool
    {
        if (isset($this->add) && count($this->add) > 0) {
            foreach ($this->add as $column) {
                $dbi->addColumn($this->name, $column->toArray());
            }
        }
        if (isset($this->drop) && count($this->drop) > 0) {
            foreach ($this->drop as $column) {
                $dbi->dropColumn($this->name, $column, true);
            }
        }

        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        return $dbi->dropTable($this->name, true);
    }

    public function apply(BaseAction $action): bool
    {
        if (isset($action->add) && count($action->add) > 0) {
            foreach ($action->add as $column) {
                $this->columns[] = $column;
            }
        }
        if (isset($action->drop) && count($action->drop) > 0) {
            $this->columns = array_filter($this->columns, function (Column $column) use ($action) {
                return !in_array($column->name, $action->drop);
            });
        }

        return false;
    }
}
