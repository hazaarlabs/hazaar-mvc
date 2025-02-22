<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Replaces newlines with <br /> tags.
 */
class Nl2br
{
    /**
     * @param string $value The string to replace newlines with <br /> tags
     */
    public function run(string $value): string
    {
        return str_replace("\n", '<br />', $value);
    }
}
