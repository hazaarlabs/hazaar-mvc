<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Migration\Enum;

enum DataType: string
{
    case INT = 'integer';
    case CHAR = 'character';
    case VARCHAR = 'varchar';
    case FLOAT = 'float';
    case NUMERIC = 'numeric';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case TIME = 'time';
    case TIMESTAMP = 'timestamp';
    case TEXT = 'text';
    case JSON = 'json';
    case JSONB = 'jsonb';
}
