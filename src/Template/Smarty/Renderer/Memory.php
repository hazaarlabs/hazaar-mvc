<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Renderer;

use Hazaar\Template\Smarty\Renderer;

class Memory extends Renderer
{
    private string $compiledTemplate;

    public function render(?string $file = null): string
    {
        return $this->compiledTemplate;
    }
}
