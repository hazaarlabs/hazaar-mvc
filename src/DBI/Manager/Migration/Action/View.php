<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class View extends BaseAction
{
    public string $name;
    public string $content;

    public function create(Adapter $dbi): bool
    {
        return $dbi->createView($this->name, $this->content);
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        return $dbi->dropView($this->name, true);
    }
}
