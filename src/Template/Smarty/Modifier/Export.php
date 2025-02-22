<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Export a variable using var_export.
 */
class Export
{
    /**
     * @param mixed $value The value to export
     */
    public function run(mixed $value): string
    {
        return var_export($value, true);
    }
}
