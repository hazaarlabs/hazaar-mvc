<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller;
use Hazaar\View;
use Hazaar\View\Layout as ViewLayout;

/**
 * @implements \ArrayAccess<string, mixed>
 */
class Layout extends HTML implements \ArrayAccess
{
    private ViewLayout $_layout;

    public function __construct(null|string|ViewLayout $layout = null)
    {
        parent::__construct();
        if ($layout instanceof ViewLayout) {
            $this->_layout = $layout;
        } else {
            $this->_layout = new ViewLayout($layout);
        }
    }

    public function __get(string $key): mixed
    {
        return $this->_layout->get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->_layout->set($key, $value);
    }

    protected function __prepare(Controller $controller): void
    {
        $this->_layout->setContent($this->getContent());
        $this->_layout->initHelpers();
        $this->_layout->runHelpers();
        $content = $this->_layout->render();
        $this->setContent($content);
    }

    /**
     * @param array<mixed> $param_arr
     */
    public function __call(string $method, array $param_arr): mixed
    {
        return call_user_func_array([
            $this->_layout,
            $method,
        ], $param_arr);
    }

    public function offsetGet($offset): mixed
    {
        return $this->_layout->get($offset);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->_layout->set($key, $value);
    }

    public function offsetUnset(mixed $key): void
    {
        $this->_layout->remove($key);
    }

    public function offsetExists(mixed $key): bool
    {
        return $this->_layout->has($key);
    }

    public function view(mixed $view, mixed $key = null): View
    {
        return $this->_layout->add($view, $key);
    }
}
