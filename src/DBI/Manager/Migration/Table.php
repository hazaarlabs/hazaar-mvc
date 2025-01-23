<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Migration;

use Hazaar\Model;

class Table extends Model
{
    public string $name;

    /**
     * @var array<Column>
     */
    public array $cols;
}
