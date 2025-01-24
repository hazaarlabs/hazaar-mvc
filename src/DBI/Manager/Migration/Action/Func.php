<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class Func extends BaseAction
{
    public string $name;
    public string $return_type;
    public string $lang;

    public function create(Adapter $dbi): bool
    {
        return $dbi->createFunction($this->name, $this->toArray());
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        return $dbi->dropFunction($this->name);
    }
}
