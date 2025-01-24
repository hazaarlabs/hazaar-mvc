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

    public function run(Adapter $dbi): bool
    {
        return $dbi->createTable($this->name, $this->columns);
    }
}
