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
                'name' => ActionName::RAISE,
                'spec' => new Raise($data),
            ];

            return;
        }
        $data['name'] = $data['action'];
        if (!isset($data['spec'])) {
            return;
        }
        $data['type'] = ActionType::tryFrom($data['type']);
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

    public function run(Adapter $dbi): bool
    {
        return $this->spec->run($dbi, $this->name);
    }
}
