<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class Index extends BaseAction
{
    public string $name;
    public string $table;

    /**
     * @var array<string>
     */
    public array $columns;
    public bool $unique = false;
    public string $using;

    public function construct(array &$data): void
    {
        // If there is no 'name' key, then this is a drop action.
        if (!isset($data['name'])) {
            $data = ['drop' => $data];
        }
    }

    public function create(Adapter $dbi): bool
    {
        return $dbi->createIndex($this->name, $this->table, $this->toArray());
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        return $dbi->dropIndex($this->name, true);
    }
}
