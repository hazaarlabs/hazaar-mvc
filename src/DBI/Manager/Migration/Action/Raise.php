<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Enum\ActionType;

class Raise extends BaseAction
{
    public string $message;

    public function construct(mixed &$data): void
    {
        $this->defineEventHook('serialized', function (array &$data) {
            if (isset($this->message)) {
                $data = [$this->message];
            }
        });
    }

    public function run(Adapter $dbi, ActionName $type): bool
    {
        if (ActionType::ERROR == $this->type) {
            throw new \Exception($this->message);
        }

        dump($this->message);

        return true;
    }
}
