<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

abstract class Renderer
{
    public function render(?string $file = null): string
    {
        return '';
    }
}
