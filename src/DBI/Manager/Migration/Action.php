<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Action\BaseAction;
use Hazaar\DBI\Manager\Migration\Action\Constraint;
use Hazaar\DBI\Manager\Migration\Action\Extension;
use Hazaar\DBI\Manager\Migration\Action\Func;
use Hazaar\DBI\Manager\Migration\Action\Index;
use Hazaar\DBI\Manager\Migration\Action\Raise;
use Hazaar\DBI\Manager\Migration\Action\Table;
use Hazaar\DBI\Manager\Migration\Action\Trigger;
use Hazaar\DBI\Manager\Migration\Action\View;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Enum\ActionType;
use Hazaar\Model;

class Action extends Model
{
    public ActionName $name;
    public ActionType $type;
    public string $message;
    public BaseAction $spec;

    public function construct(array &$data): void
    {
        if (isset($data['raise'])) {
            $data = [
                'type' => ActionType::ERROR,
                'name' => ActionName::RAISE,
                'spec' => new Raise($data),
            ];

            return;
        }
        if (isset($data['warn'])) {
            $data = [
                'type' => ActionType::WARNING,
                'name' => ActionName::RAISE,
                'spec' => new Raise($data),
            ];

            return;
        }
        if (isset($data['notice'])) {
            $data = [
                'type' => ActionType::NOTICE,
                'name' => ActionName::RAISE,
                'spec' => new Raise($data),
            ];

            return;
        }
        if (!isset($data['name']) && isset($data['action'])) {
            $data['name'] = $data['action'];
        }
        if (!is_object($data['name'])) {
            $actionName = ActionName::tryFrom($data['name']);
            if (!isset($actionName)) {
                throw new \Exception('Invalid action name: '.$data['name']);
            }
            $data['name'] = $actionName;
        }
        if (!is_object($data['type'])) {
            $actionType = ActionType::tryFrom($data['type']);
            if (!isset($actionType)) {
                throw new \Exception('Invalid action type: '.$data['type']);
            }
            $data['type'] = $actionType;
        }
        if (!isset($data['spec'])) {
            throw new \Exception("Missing action specification for action '{$data['name']->value}' of type '{$data['type']->value}'");
        }
        if (!$data['spec'] instanceof BaseAction) {
            if (!(is_array($data['spec']) || is_object($data['spec']))) {
                $data['spec'] = [$data['spec']];
            }
            $data['spec'] = match ($data['type']) {
                ActionType::EXTENSION => new Extension($data['spec']),
                ActionType::TABLE => new Table($data['spec']),
                ActionType::INDEX => new Index($data['spec']),
                ActionType::CONSTRAINT => new Constraint($data['spec']),
                ActionType::FUNC => new Func($data['spec']),
                ActionType::VIEW => new View($data['spec']),
                ActionType::TRIGGER => new Trigger($data['spec']),
                default => null
            };
        }
    }

    public function run(Adapter $dbi): bool
    {
        if (!isset($this->name)) {
            throw new \Exception('No action name found');
        }
        if (!isset($this->type)) {
            throw new \Exception('No action type found for action '.$this->name->value);
        }
        if (!isset($this->spec)) {
            throw new \Exception('No action specification found for action '.$this->name->value);
        }

        return $this->spec->run($dbi, $this->type, $this->name);
    }

    public static function create(ActionType $type, mixed $spec): self
    {
        return new self([
            'name' => ActionName::CREATE,
            'type' => $type,
            'spec' => $spec,
        ]);
    }

    protected function constructed(): void
    {
        /*
        * Simplify the action spec if the action is a drop action or a raise action
        *
        * Drop actions only need the name of the object to drop
        * Raise actions only need the message to raise
        */
        $this->defineEventHook('serialized', function (&$data) {
            if (ActionName::DROP === $this->name) {
                $data['spec'] = $this->spec->serializeDrop();
            } elseif (ActionName::RAISE === $this->name) {
                $data = ['raise' => $this->spec->message];

                return;
            }
            if (!isset($data['action'])) {
                unset($data['name']);
                $data = ['action' => $this->name->value] + $data;
            }
        });
    }
}
