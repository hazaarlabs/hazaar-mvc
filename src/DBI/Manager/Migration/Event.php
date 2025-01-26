<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration;

use Hazaar\DBI\Adapter;
use Hazaar\Model;

class Event extends Model
{
    /**
     * @var array<Action>
     */
    public array $actions;

    public function construct(array &$actions): void
    {
        if (array_key_exists('actions', $actions)) {
            return;
        }
        $actions = ['actions' => $actions];
    }

    public function run(Adapter $dbi): bool
    {
        foreach ($this->actions as $action) {
            $action->run($dbi);
        }

        return true;
    }
}
