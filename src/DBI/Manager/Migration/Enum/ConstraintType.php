<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Migration\Enum;

enum ConstraintType: string
{
    case PRIMARY = 'PRIMARY KEY';
    case FOREIGN = 'FOREIGN KEY';
    case UNIQUE = 'UNIQUE';
    case CHECK = 'CHECK';
}
