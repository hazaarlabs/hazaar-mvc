<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class View extends BaseAction
{
    public string $name;
    public string $query;

    public bool $dropFirst = false;

    public function create(Adapter $dbi): bool
    {
        return $dbi->createView($this->name, $this->query);
    }

    public function alter(Adapter $dbi): bool
    {
        if (true === $this->dropFirst) {
            $this->drop($dbi);
        }

        return $dbi->createView($this->name, $this->query, true);
    }

    public function drop(Adapter $dbi): bool
    {
        return $dbi->dropView($this->name, true);
    }

    /**
     * Apply an ALTER action to the BaseAction.
     */
    public function apply(BaseAction $action): bool
    {
        $this->query = $action->query;

        return true;
    }

    /**
     * Find the difference between two BaseActions.
     */
    public function diff(BaseAction $action): ?BaseAction
    {
        if (!$action instanceof self
            || trim($this->query) === trim($action->query)) {
            return null;
        }

        return $action;
    }
}
