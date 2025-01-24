<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class Func extends BaseAction
{
    public string $name;
    public string $return_type;
    public string $lang;

    public function run(Adapter $dbi): bool
    {
        return false;
    }
}
