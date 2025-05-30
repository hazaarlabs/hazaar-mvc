<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class Trigger extends BaseAction
{
    public string $name;
    public string $table;

    /**
     * @var array<string>
     */
    public array $events;
    public string $timing;
    public string $orientation;
    public string $content;

    public function create(Adapter $dbi): bool
    {
        return $dbi->createTrigger($this->name, $this->table, $this->toArray());
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        return $dbi->dropTrigger($this->name, $this->table, true);
    }
}
