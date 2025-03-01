<?php

declare(strict_types=1);

/**
 * @file        Hazaar/View/View.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

use Hazaar\Application\FilePath;
use Hazaar\Application\URL;
use Hazaar\Template\Smarty;
use Hazaar\View\Helper;

/**
 * The View class is used to render views in the Hazaar framework.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class View implements \ArrayAccess
{
    /**
     * @var array<mixed>
     */
    protected array $data = [];

    /**
     * View Helpers.
     *
     * @var array<Helper>
     */
    protected array $helpers = [];

    protected Application $application;

    private ?string $viewFile = null;

    /**
     * Array for storing names of initialised helpers so we only initialise them once.
     *
     * @var array<string>
     */
    private array $helpersInit = [];

    /**
     * @var array<string>
     */
    private array $prepared = [];

    /**
     * @var array<mixed>
     */
    private $requiresParam = [];

    public function __construct(string|View $view)
    {
        $this->load($view);
        $this->application = Application::getInstance();
        // if (count($inithelpers) > 0) {
        //     foreach ($inithelpers as $helper) {
        //         $this->addHelper($helper);
        //     }
        // }
    }

    public function __get(string $helper): mixed
    {
        return $this->get($helper);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    public function __unset(string $key): void
    {
        $this->remove($key);
    }

    /**
     * Returns the path to a view file.
     *
     * This method will search for a view file in the application view path and the support view path.
     *
     * @param string $view the name of the view to find
     * @param string $name the name of the view that was found
     *
     * @param-out string $name the name of the view that was found
     *
     * @return null|string the path to the view file or null if the view was not found
     */
    public static function getViewPath(string $view, string &$name = ''): ?string
    {
        $viewfile = null;
        $parts = pathinfo($view);
        $name = (('.' !== $parts['dirname']) ? $parts['dirname'].'/' : '').$parts['filename'];
        $type = FilePath::VIEW;
        /*
         * If the name begins with an @ symbol then we are trying to load the view from a
         * support file path, not the application path
         */
        if ('@' == substr($view, 0, 1)) {
            $view = substr($view, 1);
            $type = FilePath::SUPPORT;
        } else {
            $view = $name;
        }
        if (array_key_exists('extension', $parts)) {
            $viewfile = Loader::getFilePath($type, $view.'.'.$parts['extension']);
        } else {
            $extensions = ['phtml', 'tpl'];
            foreach ($extensions as $extension) {
                if ($viewfile = Loader::getFilePath($type, $view.'.'.$extension)) {
                    break;
                }
            }
        }

        return $viewfile;
    }

    public function load(string $view): void
    {
        if (Loader::isAbsolutePath($view)) {
            $this->viewFile = $view;
        } else {
            $this->viewFile = View::getViewPath($view);
            if (!$this->viewFile) {
                throw new \Exception("File not found or permission denied accessing view '{$view}'.");
            }
        }
    }

    /**
     * Returns the filename that the view was loaded from.
     */
    public function getViewFile(): string
    {
        return $this->viewFile;
    }

    /**
     * Helper/data accessor method.
     *
     * This will return a helper, if one exists with the name provided.  Otherwise it will return any view data stored with the name.
     *
     * @param string $key     the name of the helper or view data key
     * @param mixed  $default if neither a helper or view data is found this default value will be returned
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null)
    {
        if (array_key_exists($key, $this->helpers)) {
            return $this->helpers[$key];
        }
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        return $default;
    }

    /**
     * Set view data value by key.
     *
     * @param string $key   The name of the view data
     * @param mixed  $value The value to set on the view data.  Can be anything including strings, integers, arrays or objects.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Tests if view data is set with the provided key.
     *
     * @param string $key The name of the view data to look for
     *
     * @return bool true if the view data is set (even if it is set but null/empty), false otherwise
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Remove view data.
     *
     * @param string $key the name of the view data to remove
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Populate view data from an array.
     *
     * @param array<mixed> $array
     */
    public function populate(array $array): bool
    {
        $this->data = $array;

        return false;
    }

    /**
     * Extend/merge existing view data with an array.
     *
     * @param array<mixed> $array
     */
    public function extend(array $array): bool
    {
        $this->data = array_merge($this->data, $array);

        return true;
    }

    /**
     * Returns the entire current view data array.
     *
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Adds a helper to the view.
     *
     * @param array<mixed>|Helper|string $helper the name of the helper to add
     * @param array<mixed>               $args   the arguments to pass to the helper (optional)
     * @param string                     $alias  the alias for the helper (optional)
     *
     * @return bool true if the helper was added, false if the helper could not be found
     */
    public function addHelper(array|Helper|string $helper, array $args = [], ?string $alias = null): bool
    {
        if (is_array($helper)) {
            foreach ($helper as $alias => $h) {
                self::addHelper($h, [], $alias);
            }
        } elseif (is_object($helper)) {
            if (null === $alias) {
                $alias = strtolower($helper->getName());
            }
            $this->helpers[$alias] = $helper;
        } elseif (null !== $helper) {
            if (null === $alias) {
                $alias = strtolower($helper);
            }
            if (!array_key_exists($alias, $this->helpers)) {
                if (!($class = $this->findHelper($helper))) {
                    return false;
                }
                $helper = new $class($this, $args);
                $this->helpers[$alias] = $helper;
            } else {
                $helper = $this->helpers[$alias];
                $helper->extendArgs($args);
            }
        }

        return true;
    }

    /**
     * Tests if a view helper has been loaded in this view.
     *
     * @param string $helper The name of the view helper
     */
    public function hasHelper(string $helper): bool
    {
        return array_key_exists($helper, $this->helpers);
    }

    /**
     * Returns a list of all currently loaded view helpers.
     *
     * @return array<mixed>
     */
    public function getHelpers(): array
    {
        return array_keys($this->helpers);
    }

    /**
     * Remove a loaded view helper.
     *
     * @param string $helper Returns true if the helper was unloaded.  False if the view helper is not loaded to begin with.
     */
    public function removeHelper(string $helper): bool
    {
        if (!array_key_exists($helper, $this->helpers)) {
            return false;
        }
        unset($this->helpers[$helper]);

        return true;
    }

    /**
     * Retrieve a loaded view helper object.
     *
     * @param string $key The name of the view helper
     */
    public function &getHelper(string $key): ?Helper
    {
        if (!array_key_exists($key, $this->helpers)) {
            return null;
        }

        return $this->helpers[$key];
    }

    /**
     * Initialises the loaded view helpers.
     *
     * View helpers usually want to be initialised.  This gives them a chance to require any scripts or set up any
     * internal settings ready before execution of it's methods.
     *
     * @internal
     */
    public function initHelpers(): void
    {
        foreach ($this->helpers as $helper) {
            $name = get_class($helper);
            if (in_array($name, $this->helpersInit)) {
                continue;
            }
            $helper->initialise();
            $this->helpersInit[] = $name;
        }
    }

    /**
     * Runs loaded view helpers.
     *
     * @internal
     */
    public function runHelpers(): void
    {
        foreach ($this->helpers as $helper) {
            $helper->run($this);
        }
    }

    /**
     * Render the view.
     *
     * This method is responsible for loading the view files from disk, rendering it and returning it's output.
     *
     * @param array<mixed> $data The data to pass to the view.  This data will be merged with any existing view data.
     *
     * @internal
     */
    public function render(?array $data = null): string
    {
        $output = '';
        $parts = pathinfo($this->viewFile);
        if ('tpl' == ake($parts, 'extension')) {
            $template = new Smarty();
            $template->loadFromFile($this->viewFile);
            $template->registerFunctionHandler($this);
            $output = $template->render($data ?? $this->data);
        } else {
            ob_start();
            if (!($file = $this->getViewFile()) || !file_exists($file)) {
                throw new \Exception("View does not exist ({$file})", 404);
            }

            include $file;
            $output = ob_get_contents();
            ob_end_clean();
        }

        return $output;
    }

    /**
     * Render a partial view in the current view.
     *
     * This method can be called from inside a view source file to include another view source file.
     *
     * @param string            $view The name of the view to include, relative to the current view.  This means that if the view is in the same
     *                                directory, it is possible to just name the view.  If it is in a sub directly, include the path relative
     *                                to the current view.  Using parent references (..) will also work.
     * @param array<mixed>|bool $data The data parameter can be either TRUE to indicate that all view data should be passed to the
     *                                partial view, or an array of data to pass instead.  By default, no view data is passed to the partial view.
     *
     * @return string The rendered view output will be returned.  This can then be echo'd directly to the client.
     */
    public function partial(string $view, null|array|bool $data = null, bool $mergedata = false): string
    {
        if (array_key_exists($view, $this->prepared)) {
            return $this->prepared[$view];
        }
        /*
         * This converts "absolute paths" to paths that are relative to `\Hazaar\Application\FilePath::VIEW`.
         *
         * Relative paths are then made relative to the current view (using it's absolute path).
         */
        if ('/' === substr($view, 0, 1)) {
            $view = substr($view, 1);
        } else {
            $view = dirname($this->viewFile).'/'.$view.'.phtml';
        }
        $output = '';
        $partial = new View($view);
        $partial->addHelper($this->helpers);
        if (is_array($data)) {
            $partial->extend($data);
        } elseif (true === $data) {
            $partial->extend($this->data);
        }
        $output = $partial->render();
        if (true === $mergedata) {
            $this->extend($partial->getData());
        }

        return $output;
    }

    /**
     * Prepare a partial view for later rendering.
     *
     * This method is similar to the `partial` method, but instead of rendering the view immediately, it will prepare the view for rendering later.
     *
     * @param string            $view The name of the view to include, relative to the current view.  This means that if the view is in the same
     *                                directory, it is possible to just name the view.  If it is in a sub directly, include the path relative
     *                                to the current view.  Using parent references (..) will also work.
     * @param array<mixed>|bool $data The data parameter can be either TRUE to indicate that all view data should be passed to the
     */
    public function preparePartial(string $view, null|array|bool $data = null): void
    {
        $content = $this->partial($view, $data, true);
        $this->prepared[$view] = $content;
    }

    /**
     * Add a required parameter to the view.
     *
     * This is used to add a required parameter to the view.  If the parameter is not set when the view is rendered, an exception will be thrown.
     *
     * @param array<mixed> $array an array of required parameter names
     */
    public function setRequiresParam(array $array): void
    {
        $this->requiresParam = array_merge($this->requiresParam, $array);
    }

    /**
     * Render a partial view multiple times on an array.
     *
     * This basically calls `$this->partial` for each element in an array
     *
     * @param string       $view the partial view to render
     * @param array<mixed> $data a data array, usually multi-dimensional, that each element will be passed to the partial view
     *
     * @return string the rendered view output
     */
    public function partialLoop(string $view, array $data): string
    {
        $output = '';
        foreach ($data as $d) {
            $output .= $this->partial($view, $d);
        }

        return $output;
    }

    /**
     * Generates a URL based on the provided controller, action, parameters, and absolute flag.
     *
     * @param string       $controller the name of the controller
     * @param string       $action     the name of the action
     * @param array<mixed> $params     an array of parameters to be included in the URL
     * @param bool         $absolute   determines whether the generated URL should be absolute or relative
     *
     * @return URL the generated URL
     */
    public function url(?string $controller = null, ?string $action = null, array $params = [], bool $absolute = false): URL
    {
        return $this->application->getURL($controller, $action, $params, $absolute);
    }

    /**
     * Returns a date string formatted to the current set date format.
     */
    public function date(DateTime|string $date): string
    {
        if (!$date instanceof DateTime) {
            $date = new DateTime($date);
        }

        return $date->date();
    }

    /**
     * Return a date/time type as a timestamp string.
     *
     * This is for making it quick and easy to output consistent timestamp strings.
     */
    public static function timestamp(DateTime|string $value): string
    {
        if (!$value instanceof DateTime) {
            $value = new DateTime($value);
        }

        return $value->timestamp();
    }

    /**
     * Return a formatted date as a string.
     *
     * @param mixed  $value  This can be practically any date type.  Either a \Hazaar\DateTime object, epoch int, or even a string.
     * @param string $format Optionally specify the format to display the date.  Otherwise the current default is used.
     *
     * @return string the nicely formatted datetime string
     */
    public static function datetime(mixed $value, ?string $format = null): string
    {
        if (!$value instanceof DateTime) {
            $value = new DateTime($value);
        }
        if ($format) {
            return $value->format($format);
        }

        return $value->datetime();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null === $offset) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Find a helper class for the given name.
     *
     * This method searches for view helper classes in the specified search paths.
     * The order of the search paths is important because it allows apps to override built-in helpers.
     *
     * @param string $name the name of the helper class to find
     *
     * @return null|string the fully qualified class name of the helper, or null if not found
     */
    private function findHelper(string $name): ?string
    {
        /**
         * Search paths for view helpers. The order here matters because apps should be able to override built-in helpers.
         */
        $search_prefixes = ['\Application\Helper\View', '\Hazaar\View\Helper'];
        $name = \ucfirst($name);
        foreach ($search_prefixes as $prefix) {
            $class = $prefix.'\\'.$name;
            if (\class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}
