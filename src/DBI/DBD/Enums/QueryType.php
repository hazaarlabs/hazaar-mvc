<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD\Enums;

enum QueryType: string
{
    case SELECT = 'SELECT';
    case INSERT = 'INSERT';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case TRUNCATE = 'TRUNCATE';
}
