<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Dump the variable using var_dump.
 */
class Dump
{
    /**
     * @param mixed $value the variable to dump
     */
    public function run(mixed $value): string
    {
        ob_start();
        var_dump($value);
        $output = ob_get_clean();
        if ('<pre' === substr($output, 0, 4)) {
            $offsetCR = strpos($output, "\n") + 1;
            $suffixPRE = strrpos($output, '</pre>');
            $offsetSmallOpen = strpos($output, '<small>', $offsetCR);
            $offsetSmallClose = strpos($output, '</small>', $offsetSmallOpen) + 8;
            $output = substr($output, $offsetSmallClose, $suffixPRE - $offsetSmallClose);
        }

        return trim($output);
    }
}
