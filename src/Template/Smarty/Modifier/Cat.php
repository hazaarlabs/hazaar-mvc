<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Concatenate strings.
 */
class Cat
{
    /**
     * @param string ...$args The strings to concatenate.
     */
    public function run(string ...$args): string
    {
        return implode('', $args);
    }
}
