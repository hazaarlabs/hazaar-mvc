<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Controller/Helper.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Controller;

use Hazaar\Controller;

abstract class Helper
{
    protected ?Controller $controller;

    /**
     * @var array<mixed>
     */
    protected array $args;

    /**
     * @param array<mixed> $args
     */
    final public function __construct(?Controller $controller = null, array $args = [])
    {
        $this->controller = $controller;
        $this->args = $args;
        $this->import($args);
    }

    /**
     * Retrieves the name of the current class without the namespace.
     *
     * This method uses the `get_class` function to get the fully qualified class name
     * of the current object, and then extracts the class name by removing the namespace
     * part.
     *
     * @return string the name of the current class without the namespace
     */
    public function getName(): string
    {
        $class = get_class($this);

        return substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * @param array<mixed> $args
     */
    public function import($args = []): void
    {
        // Do nothing by default.
    }
}
