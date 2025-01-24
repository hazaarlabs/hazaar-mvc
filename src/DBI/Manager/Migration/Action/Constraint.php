<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class Constraint extends BaseAction
{
    public string $name;
    public string $table;

    /**
     * @var array<string>
     */
    public array $columns;

    public function run(Adapter $dbi): bool
    {
        return false;
        // return $dbi->createConstraint($this->table, $this->name, $this->columns);
    }
}
