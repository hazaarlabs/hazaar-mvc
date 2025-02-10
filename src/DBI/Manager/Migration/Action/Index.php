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

    public function create(Adapter $dbi): bool
    {
        if (!isset($this->name)) {
            $dbi->log('ERROR: Index name not set for create action');

            return false;
        }
        if (!isset($this->table)) {
            $dbi->log("ERROR: Table not set creating index '{$this->name}'");

            return false;
        }

        return $dbi->createIndex($this->name, $this->table, $this->toArray());
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        if (!isset($this->drop)) {
            return false;
        }
        foreach ($this->drop as $index) {
            if (!$dbi->dropIndex($index, true)) {
                return false;
            }
        }

        return true;
    }
}
