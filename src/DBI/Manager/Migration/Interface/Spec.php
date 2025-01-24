<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Interface;

use Hazaar\DBI\Adapter;

interface Spec
{
    public function run(Adapter $dbi): bool;
}
