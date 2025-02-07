<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Sync;

use Hazaar\Model;

class Item extends Model
{
    public string $message;
    public string $table;

    /**
     * @var array<mixed>
     */
    public array $rows;
}
