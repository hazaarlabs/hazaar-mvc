<?php

declare(strict_types=1);

namespace Hazaar\Template\Exception;

use Hazaar\Template\Smarty\Enum\RendererType;

class SmartyRendererTypeNotSupported extends \Exception
{
    public function __construct(RendererType $type)
    {
        parent::__construct("The renderer type '{$type->value}' is not supported!");
    }
}
