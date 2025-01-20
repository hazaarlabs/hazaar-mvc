<?php

declare(strict_types=1);

namespace Hazaar\Application;

enum FilePath: string
{
    case ROOT = 'root';
    case APPLICATION = 'application';
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
            'Models' => self::MODEL,
            'Controllers' => self::CONTROLLER,
            'Helpers' => self::HELPER,
            default => null,
        };
    }
}
