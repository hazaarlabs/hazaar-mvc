<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Event;
use Hazaar\Model;

class Migration extends Model
{
    public string $message;

    /**
     * Raises an exception if the migration is not compatible with the current database schema.
     */
    public string $raise;

    /**
     * List of migration versions that are required to be run before this migration.
     *
     * @var array<int>
     */
    public array $requires;

    /**
     * @var array<string>
     */
    public array $exec;
    public Event $up;
    public Event $down;

    public function replay(Adapter $dbi): bool
    {
        if (!isset($this->up)) {
            throw new \RuntimeException('Migration has no up event defined.');
        }

        return $this->up->run($dbi);
    }

    public function rollback(Adapter $dbi): bool
    {
        if (!isset($this->down)) {
            throw new \RuntimeException('Migration has no down event defined.');
        }

        return $this->down->run($dbi);
    }
}
