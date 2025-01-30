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
        $this->defineEventHook('serialized', function (&$data) {
            if (ActionName::DROP === $this->name) {
                $data['spec'] = $this->spec->name;
            }
        });
        if (isset($data['raise'])) {
            $data = [
                'type' => ActionType::RAISE,
                'name' => ActionName::RAISE,
                'spec' => new Raise($data),
            ];

            return;
        }
        if (!isset($data['spec'])) {
            return;
        }
        if (!isset($data['name']) && isset($data['action'])) {
            $data['name'] = $data['action'];
        }
        if (!is_object($data['type'])) {
            $data['type'] = ActionType::tryFrom($data['type']);
        }
        if (!$data['spec'] instanceof BaseAction) {
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
        return $this->spec->run($dbi, $this->name);
    }

    public static function create(ActionType $type, mixed $spec): self
    {
        return new self([
            'name' => ActionName::CREATE,
            'type' => $type,
            'spec' => $spec,
        ]);
    }
}
