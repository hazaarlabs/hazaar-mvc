<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Action\Component\ConstraintReference;

class Constraint extends BaseAction
{
    public string $name;
    public string $table;
    public string $type;
    public string $column;
    public ConstraintReference $references;

    /**
     * @var array<string>
     */
    public array $drop;

    public function construct(array &$data): void
    {
        if (!isset($data['name'])) {
            $data = ['drop' => $data];
        }
    }

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
        return $dbi->dropConstraint($this->name, $this->table, true);
    }
}
