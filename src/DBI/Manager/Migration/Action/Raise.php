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
        $this->message = $data['raise'] ?? $data['warn'] ?? $data['notice'] ?? '';
    }

    public function run(Adapter $dbi, ActionType $type, ActionName $name): bool
    {
        if (ActionType::ERROR === $type) {
            throw new \Exception($this->message);
        }
        $dbi->log(strtoupper($type->value).': '.$this->message);

        return true;
    }
}
