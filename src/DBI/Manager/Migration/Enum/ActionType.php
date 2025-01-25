<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Enum;

enum ActionType: string
{
    case EXTENSION = 'extension';
    case TABLE = 'table';
    case CONSTRAINT = 'constraint';
    case INDEX = 'index';
    case VIEW = 'view';
    case FUNC = 'function';
    case TRIGGER = 'trigger';
    case RAISE = 'raise';
}
