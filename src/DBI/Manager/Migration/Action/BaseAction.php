<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Interface\Spec;
use Hazaar\Model;

abstract class BaseAction extends Model implements Spec
{
    public function run(Adapter $dbi, ActionName $actionName): bool
    {
        return match ($actionName) {
            ActionName::CREATE => $this->create($dbi),
            ActionName::ALTER => $this->alter($dbi),
            ActionName::DROP => $this->drop($dbi),
            default => false,
        };
    }

    public function create(Adapter $dbi): bool
    {
        return false;
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        return false;
    }

    public function apply(self $action): bool
    {
        return false;
    }

    public function diff(self $action): ?self
    {
        return null;
    }
}
