<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Action\BaseAction;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Enum\ActionType;
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
        $this->defineEventHook('serialized', function (array &$array) {
            if (isset($array['actions'])) {
                $array = $array['actions'];
            }
        });
    }

    public function run(Adapter $dbi): bool
    {
        foreach ($this->actions as $action) {
            if (!$action->run($dbi)) {
                $dbi->log("ERROR: Failed to {$action->name->value} {$action->type->value}: {$action->spec->name}");

                return false;
            }
        }

        return true;
    }

    public function add(ActionName $actionName, ActionType $actionType, BaseAction $actionSpec): Action
    {
        $this->actions[] = $action = new Action([
            'name' => $actionName,
            'type' => $actionType,
            'spec' => $actionSpec,
        ]);

        return $action;
    }
}
