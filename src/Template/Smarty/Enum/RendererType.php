<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Enum;

enum RendererType: string
{
    case AUTO = 'auto';
    case MEMORY = 'memory';
    case FILE = 'file';
}
