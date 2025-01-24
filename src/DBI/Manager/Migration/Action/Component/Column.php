<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action\Component;

use Hazaar\DBI\Manager\Migration\Enum\DataType;
use Hazaar\Model;

class Column extends Model
{
    public string $name;
    public mixed $default;
    public bool $not_null;
    public string $type;
    public bool $primary_key;
}
