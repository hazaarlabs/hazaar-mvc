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

    /**
     * @var array<string>
     */
    public array $column;
    public ConstraintReference $references;

    public function construct(mixed &$data): void
    {
        if (!is_array($data['column'])) {
            $data['column'] = [$data['column']];
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
        if (!$dbi->dropConstraint($this->name, $this->table, true)) {
            return false;
        }

        return true;
    }

    public function serializeDrop(): mixed
    {
        return [
            'name' => $this->name,
            'table' => $this->table,
        ];
    }
}
