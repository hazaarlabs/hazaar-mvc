<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;

class Raise extends BaseAction
{
    public string $message;

    public function construct(array &$data): void
    {
        $data = ['message' => $data['raise'] ?? 'Unknown migration error'];
    }

    public function run(Adapter $dbi, ActionName $type): bool
    {
        throw new \Exception($this->message);
    }
}
