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
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        return $dbi->dropTable($this->name);
    }
}
