<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Action\Exception\CreateFailed;

class Func extends BaseAction
{
    public string $name;
    public string $return_type;
    public string $lang;
    public string $body;

    public function create(Adapter $dbi): bool
    {
        if (!isset($this->body)) {
            throw new CreateFailed('No function body', $this->name);
        }

        return $dbi->createFunction($this->name, $this->toArray());
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        foreach ($this->drop as $dropItem) {
            $dbi->dropFunction($dropItem, null, false, true);
        }

        return true;
    }

    public function apply(BaseAction $action): bool
    {
        if (!$action instanceof self) {
            return false;
        }
        $this->body = $action->body;

        return true;
    }

    public function diff(BaseAction $action): ?BaseAction
    {
        if ($this->body !== $action->body) {
            return $action;
        }

        return null;
    }
}
