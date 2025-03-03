<?php

declare(strict_types=1);

namespace Hazaar\Template\Exception;

class SmartyTemplateFunctionNotFound extends \Exception
{
    public function __construct(string $function)
    {
        parent::__construct('Smarty template function not found: '.$function);
    }
}
