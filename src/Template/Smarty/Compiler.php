<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

class Compiler
{
    private string $compiledTemplate;

    public function compile(?string $file = null): string
    {
        return $this->compiledTemplate;
    }
}
