<?php

declare(strict_types=1);

namespace Hazaar\Controller\Action;

use Hazaar\Controller\Action\Exception\NoContent;
use Hazaar\Controller\Response\HTML;
use Hazaar\View;
use Hazaar\View\Helper;
use Hazaar\View\Layout;

/**
 * Class ViewRenderer.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class ViewRenderer implements \ArrayAccess
{
    private ?View $view = null;

    /**
     * @var array<string, mixed>
     */
    private array $_data = [];

    public function __set(string $key, mixed $value)
    {
        $this->_data[$key] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->_data[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->_data[$name]);
    }

    // Helper execution call.  This renders the layout file.
    public function exec(HTML $response): void
    {
        $content = $this->render($this->view);
        if (!$content) {
            throw new NoContent(get_class($this->view));
        }
        $response->setContent($content);
    }

    /**
     * Adds a helper to the view renderer.
     *
     * @param string       $helper the name of the helper to add
     * @param array<mixed> $args   an optional array of arguments to pass to the helper
     * @param null|string  $alias  an optional alias for the helper
     *
     * @return bool the added ViewHelper instance, or null if the view is not set
     */
    public function addHelper(string $helper, array $args = [], ?string $alias = null): bool
    {
        if (!$this->view instanceof View) {
            return false;
        }

        return $this->view->addHelper($helper, $args, $alias);
    }

    /**
     * Retrieves the helpers associated with the current view.
     *
     * @return array<Helper> an array of helpers or null if no view is set
     */
    public function getHelpers(): ?array
    {
        if ($this->view) {
            return $this->view->getHelpers();
        }

        return null;
    }

    /**
     * Removes a helper from the view renderer.
     *
     * @param string $name the name of the helper to remove
     */
    public function removeHelper(string $name): void
    {
        if ($this->view) {
            $this->view->removeHelper($name);
        }
    }

    /**
     * Magic method to get the value of a property.
     *
     * @param string $key the name of the property to get
     *
     * @return mixed the value of the property
     */
    public function &__get(string $key): mixed
    {
        if ($this->view && $this->view->hasHelper($key)) {
            return $this->view->getHelper($key);
        }

        return $this->_data[$key];
    }

    /**
     * Populates the view renderer with data.
     *
     * @param array<mixed> $array the array of data to populate the view renderer with
     */
    public function populate(array $array): void
    {
        $this->_data = $array;
    }

    /**
     * Extends the data available on any defined views.
     *
     * @param array<mixed> $array the array of data to extend the view renderer with
     */
    public function extend(array $array): void
    {
        $this->_data = array_merge($this->_data, $array);
    }

    /**
     * Checks if a view exists.
     *
     * @param string $view the name of the view to check
     *
     * @return bool returns true if the view exists, false otherwise
     */
    public function hasView(string $view): bool
    {
        return null !== View::getViewPath($view);
    }

    /**
     * Sets the layout for the view.
     *
     * If the current view is an instance of `Layout`, it loads the specified layout.
     * Otherwise, if the specified layout is not an instance of `Layout`, it creates a new `Layout` instance.
     * Finally, it sets the view to the specified layout.
     *
     * @param Layout|string $view the layout or view to set
     */
    public function layout(Layout|string $view): void
    {
        if ($this->view instanceof Layout) {
            $this->view->load($view);
        } else {
            if (!$view instanceof Layout) {
                $view = new Layout($view);
            }
            $this->view = $view;
        }
    }

    /**
     * Sets the view renderer to disable layout rendering.
     *
     * This method sets the view property to null, which indicates that the layout rendering should be disabled.
     * When the layout rendering is disabled, only the view template will be rendered without any surrounding layout.
     */
    public function setNoLayout(): void
    {
        $this->view = null;
    }

    /**
     * Renders a view.
     *
     * @param string|View  $view The view to render. It can be either a string representing the view file path or an instance of the View class.
     * @param array<mixed> $data The data to pass to the view
     */
    public function view(string|View $view, array $data = []): void
    {
        if (!$view instanceof View) {
            $view = new View($view);
        }
        if ($this->view instanceof Layout) {
            $this->view->add($view);
        } else {
            $this->view = $view;
        }
        $this->populate($data);
    }

    /*
     * Render the view/layout.
     *
     * - Supports layout views and renders all views inside the layout.
     * - Renders to the output buffer, then grabs the buffer and returns it.
     */
    public function render(?View $view = null): string
    {
        return $this->renderView($view);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->_data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->_data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->_data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->_data[$offset]);
    }

    /**
     * Renders a view and returns the rendered output as a string.
     *
     * @param View $view the view object to render
     *
     * @return string the rendered output of the view
     */
    private function renderView(View $view): string
    {
        $view->extend($this->_data);
        $view->initHelpers();
        $view->runHelpers();

        return $view->render();
    }
}
