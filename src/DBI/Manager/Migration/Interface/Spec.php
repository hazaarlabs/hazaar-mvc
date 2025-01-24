<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Interface;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;

interface Spec
{
    public function run(Adapter $dbi, ActionName $name): bool;

    public function create(Adapter $dbi): bool;

    public function alter(Adapter $dbi): bool;

    public function drop(Adapter $dbi): bool;
}
