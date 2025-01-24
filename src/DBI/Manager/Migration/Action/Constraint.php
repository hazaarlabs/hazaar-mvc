<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class Constraint extends BaseAction
{
    public string $name;
    public string $table;
    public string $type;
    public string $column;

    public function create(Adapter $dbi): bool
    {
        return $dbi->addConstraint($this->name, $this->toArray());
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        return $dbi->dropConstraint($this->name, $this->table);
    }
}
