<?php

declare(strict_types=1);

namespace Hazaar\View;

use Hazaar\Application;
use Hazaar\View;
use Hazaar\View\Interface\Helper as HelperInterface;

abstract class Helper implements HelperInterface
{
    protected View $view;
    protected Application $application;

    /**
     * @var array<mixed>
     */
    protected array $args;

    /**
     * Helper constructor.
     *
     * @param array<mixed> $args
     */
    final public function __construct(?View $view, array $args = [])
    {
        $this->application = Application::getInstance();
        $this->view = $view;
        $this->args = $args;
        $this->import();
    }

    public function __get(string $method): mixed
    {
        return $this->view->__get($method);
    }

    /**
     * @param array<mixed> $args
     */
    public function initialise(?array $args = null): void
    {
        if (null !== $args) {
            $args = $this->args;
        }
        $this->init($args);
    }

    /**
     * @param array<mixed> $args
     */
    public function extendArgs(array $args): void
    {
        $this->args = array_merge($this->args, $args);
    }

    public function set(string $arg, mixed $value): void
    {
        $this->args[$arg] = $value;
    }

    public function get(string $arg): mixed
    {
        if (array_key_exists($arg, $this->args)) {
            return $this->args[$arg];
        }

        return null;
    }

    public function getName(): string
    {
        $class = get_class($this);

        return substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * @param array<mixed> $args
     */
    public function requires(string $helper, array $args = []): void
    {
        if (!$this->view->hasHelper($helper)) {
            $this->view->addHelper($helper, $args);
        }
    }

    // Placeholder functions
    public function import(): void
    {
        // Do nothing by default.
    }

    public function init(array $args = []): bool
    {
        return true;
    }

    public function run(View $view): bool
    {
        return false; // Helper should at least do something at runtime.
    }
}
