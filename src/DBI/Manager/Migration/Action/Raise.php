<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class Raise extends BaseAction
{
    public string $message;

    public function construct(array &$data): void
    {
        $data = ['message' => $data['raise'] ?? 'Unknown migration error'];
    }

    public function run(Adapter $dbi): bool
    {
        throw new \Exception($this->message);
    }
}
