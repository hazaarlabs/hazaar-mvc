<?php

declare(strict_types=1);

namespace Hazaar\View;

/**
 * @brief       Interface for objects that are writable as a string.
 */
interface Viewable
{
    /**
     * Magic method to call the render() method.
     */
    public function __toString();

    /**
     * Required method to render the object as a string.
     */
    public function renderObject(): string;
}
