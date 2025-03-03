<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Enum;

enum RendererType: string
{
    case AUTO = 'auto';
    case EVAL = 'eval';
    case PHP = 'php';
}
