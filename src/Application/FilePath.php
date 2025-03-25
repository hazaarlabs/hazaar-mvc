<?php

declare(strict_types=1);

namespace Hazaar\Application;

enum FilePath: string
{
    case ROOT = 'root';
    case APPLICATION = 'app';
    case CONFIG = 'config';
    case MODEL = 'model';
    case VIEW = 'view';
    case CONTROLLER = 'controller';
    case HELPER = 'helper';
    case SERVICE = 'service';
    case SUPPORT = 'support';
    case LIB = 'library';
    case PUBLIC = 'public';
    case RUNTIME = 'runtime';
    case DB = 'db';

    public static function fromApplicationNamespace(string $namespace): ?self
    {
        return match ($namespace) {
            'Model' => self::MODEL,
            'Controller' => self::CONTROLLER,
            'Helper' => self::HELPER,
            default => null,
        };
    }
}
