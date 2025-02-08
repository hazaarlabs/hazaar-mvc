<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Sync\Enums;

enum RowStatus: string
{
    case NEW = 'new';
    case UPDATED = 'updated';
    case UNCHANGED = 'unchanged';
}
