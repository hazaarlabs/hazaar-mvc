<?php

namespace Hazaar\Template\Exception;

class SmartyTemplateError extends \Exception
{
    public function __construct(?\Throwable $previous = null, ?string $sourceFile = null)
    {
        $msg = 'Smarty template error'
            .($sourceFile ? ' rendering '.$sourceFile : '')
            .': '.$previous->getMessage();
        parent::__construct($msg, $previous->getCode(), $previous);
    }
}
