<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Enum;

enum ActionName: string
{
    case CREATE = 'create';
    case ALTER = 'alter';
    case DROP = 'drop';
    case RENAME = 'rename';
    case RAISE = 'raise';
}
