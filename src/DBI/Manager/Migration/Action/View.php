<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class View extends BaseAction
{
    public string $name;
    public string $content;

    public function run(Adapter $dbi): bool
    {
        return false;
    }
}
