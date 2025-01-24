<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;
use Hazaar\Model;

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
    public string $function;

    public function run(Adapter $dbi): bool
    {
        return false;
    }
}
