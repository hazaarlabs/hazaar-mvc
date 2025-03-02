<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Renderer;

use Hazaar\Template\Exception\SmartyTemplateError;
use Hazaar\Template\Smarty\Renderer;

class Memory extends Renderer
{
    public function render(string $id, string $code): object
    {
        $errors = error_reporting();
        error_reporting(0);

        try {
            eval($code);
        } catch (\Throwable $e) {
            throw new SmartyTemplateError($e);
        } finally {
            error_clear_last();
            error_reporting($errors);
        }

        return new $id();
    }
}
