<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class Extension extends BaseAction
{
    public string $name;

    /**
     * @var array<string>
     */
    public array $extensions;

    public function construct(array &$data): void
    {
        $data = ['extensions' => $data];
    }

    public function run(Adapter $dbi): bool
    {
        foreach ($this->extensions as $extension) {
            if (!$dbi->createExtension($extension)) {
                return false;
            }
        }

        return true;
    }
}
