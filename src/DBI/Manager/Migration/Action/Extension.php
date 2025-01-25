<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;

class Extension extends BaseAction
{
    /**
     * @var array<string>
     */
    public array $extensions;

    public function construct(array &$data): void
    {
        $data = ['extensions' => $data];
    }

    public function create(Adapter $dbi): bool
    {
        foreach ($this->extensions as $extension) {
            if (!$dbi->createExtension($extension)) {
                return false;
            }
        }

        return true;
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        foreach ($this->extensions as $extension) {
            if (!$dbi->dropExtension($extension)) {
                return false;
            }
        }

        return true;
    }
}
