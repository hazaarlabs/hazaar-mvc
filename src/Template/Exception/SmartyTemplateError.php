<?php

namespace Hazaar\Template\Exception;

class SmartyTemplateError extends \Exception
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Smarty template error: '.$previous->getMessage(), $previous->getCode(), $previous);
    }
}
