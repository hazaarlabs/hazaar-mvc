<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Migration;

use Hazaar\DBI\Schema\Migration\Enum\DataType;
use Hazaar\Model;

class Column extends Model
{
    public string $name;
    public mixed $default;
    public bool $not_null;
    public DataType $type;
}
