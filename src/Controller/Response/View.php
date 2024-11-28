<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller;
use Hazaar\View as HazaarView;

class View extends HTML
{
    private ?HazaarView $_view = null;

    /**
     * @var array<string, mixed>
     */
    private array $__data = [];

    /**
     * @param null|array<string, mixed> $data
     */
    public function __construct(HazaarView|string $view, ?array $data = null)
    {
        parent::__construct();
        $this->load($view);
        if (null !== $data) {
            $this->populate($data);
        }
    }

    protected function __prepare(Controller $controller): void
    {
        $this->_view->populate($this->__data);
        $content = $this->_view->render();
        $this->setContent($content);
    }

    /**
     * @param array<mixed> $param_arr
     */
    public function __call(string $method, array $param_arr): mixed
    {
        return call_user_func_array([$this->_view, $method], $param_arr);
    }

    public function __set(mixed $key, mixed $value): void
    {
        $this->__data[$key] = $value;
    }

    public function &__get(mixed $key): mixed
    {
        return $this->__data[$key];
    }

    /**
     * @param array<string,mixed> $values
     */
    public function populate(array $values): void
    {
        $this->__data = $values;
    }

    public function load(HazaarView|string $view): void
    {
        if ($view instanceof HazaarView) {
            $this->_view = $view;
        } else {
            $this->_view = new HazaarView($view);
        }
    }

    public function getContent(): string
    {
        return $this->_view->render($this->__data);
    }
}
