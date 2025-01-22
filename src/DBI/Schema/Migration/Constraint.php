<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Migration;

use Hazaar\DBI\Schema\Migration\Enum\ConstraintType;
use Hazaar\Model;

class Constraint extends Model
{
    public string $name;
    public string $table;
    public string $column;
    public ConstraintType $type;
}
