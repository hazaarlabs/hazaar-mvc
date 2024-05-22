<?php

declare(strict_types=1);

/**
 * @file        Hazaar/View/View.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

use Hazaar\Application\URL;
use Hazaar\View\Helper;

/**
 * The View class is used to render views in the Hazaar MVC framework.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class View implements \ArrayAccess
{
    public ?string $name = null;

    /**
     * @var array<mixed>
     */
    protected array $_data = [];

    /**
     * View Helpers.
     *
     * @var array<Helper>
     */
    protected array $_helpers = [];

    protected Application $application;

    private ?string $_viewfile = null;

    /**
     * Array for storing names of initialised helpers so we only initialise them once.
     *
     * @var array<string>
     */
    private array $_helpers_init = [];

    /**
     * @var array<string>
     */
    private array $_prepared = [];

    /**
     * @var array<mixed>
     */
    private $_requires_param = [];

    /**
     * @param array<string> $init_helpers
     */
    public function __construct(string|View $view, array $init_helpers = [])
    {
        $this->load($view);
        $this->application = Application::getInstance();
        if (count($init_helpers) > 0) {
            foreach ($init_helpers as $helper) {
                $this->addHelper($helper);
            }
        }
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

    public static function getViewPath(string $view, ?string &$name): ?string
    {
        $viewfile = null;
        $parts = pathinfo($view);
        $name = (('.' !== $parts['dirname']) ? $parts['dirname'].'/' : '').$parts['filename'];
        $type = FILE_PATH_VIEW;
        /*
         * If the name begins with an @ symbol then we are trying to load the view from a
         * support file path, not the application path
         */
        if ('@' == substr($view, 0, 1)) {
            $view = substr($view, 1);
            $type = FILE_PATH_SUPPORT;
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
            $this->_viewfile = $view;
        } else {
            $this->_viewfile = View::getViewPath($view, $this->name);
            if (!$this->_viewfile) {
                throw new Exception("File not found or permission denied accessing view '{$this->name}'.");
            }
        }
    }

    /**
     * Returns the name of the view.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the filename that the view was loaded from.
     */
    public function getViewFile(): string
    {
        return $this->_viewfile;
    }

    /**
     * Helper/data accessor method.
     *
     * This will return a helper, if one exists with the name provided.  Otherwise it will return any view data stored with the name.
     *
     * @param mixed $helper  the name of the helper or view data key
     * @param mixed $default if neither a helper or view data is found this default value will be returned
     *
     * @return mixed
     */
    public function get($helper, $default = null)
    {
        if (array_key_exists($helper, $this->_helpers)) {
            return $this->_helpers[$helper];
        }
        if (array_key_exists($helper, $this->_data)) {
            return $this->_data[$helper];
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
        $this->_data[$key] = $value;
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
        return array_key_exists($key, $this->_data);
    }

    /**
     * Remove view data.
     *
     * @param string $key the name of the view data to remove
     */
    public function remove(string $key): void
    {
        unset($this->_data[$key]);
    }

    /**
     * Populate view data from an array.
     *
     * @param array<mixed> $array
     */
    public function populate(array $array): bool
    {
        if (!is_array($array)) {
            return false;
        }
        $this->_data = $array;

        return false;
    }

    /**
     * Extend/merge existing view data with an array.
     *
     * @param array<mixed> $array
     */
    public function extend($array): bool
    {
        if (!is_array($array)) {
            return false;
        }
        $this->_data = array_merge($this->_data, $array);

        return true;
    }

    /**
     * Returns the entire current view data array.
     *
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->_data;
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
            if (!$helper instanceof Helper) {
                return false;
            }
            if (null === $alias) {
                $alias = strtolower($helper->getName());
            }
            $this->_helpers[$alias] = $helper;
        } elseif (null !== $helper) {
            if (null === $alias) {
                $alias = strtolower($helper);
            }
            if (!array_key_exists($alias, $this->_helpers)) {
                if (!($class = $this->findHelper($helper))) {
                    return false;
                }
                $helper = new $class($this, $args);
                $this->_helpers[$alias] = $helper;
            } else {
                if (($helper = $this->_helpers[$alias]) instanceof Helper) {
                    $helper->extendArgs($args);
                }
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
        return array_key_exists($helper, $this->_helpers);
    }

    /**
     * Returns a list of all currently loaded view helpers.
     *
     * @return array<mixed>
     */
    public function getHelpers(): array
    {
        return array_keys($this->_helpers);
    }

    /**
     * Remove a loaded view helper.
     *
     * @param string $helper Returns true if the helper was unloaded.  False if the view helper is not loaded to begin with.
     */
    public function removeHelper(string $helper): bool
    {
        if (!array_key_exists($helper, $this->_helpers)) {
            return false;
        }
        unset($this->_helpers[$helper]);

        return true;
    }

    /**
     * Retrieve a loaded view helper object.
     *
     * @param string $key The name of the view helper
     */
    public function &getHelper(string $key): ?Helper
    {
        if (!array_key_exists($key, $this->_helpers)) {
            return null;
        }

        return $this->_helpers[$key];
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
        foreach ($this->_helpers as $helper) {
            if (!$helper instanceof Helper) {
                continue;
            }
            $name = get_class($helper);
            if (in_array($name, $this->_helpers_init)) {
                continue;
            }
            $helper->initialise();
            $this->_helpers_init[] = $name;
        }
    }

    /**
     * Runs loaded view helpers.
     *
     * @internal
     */
    public function runHelpers(): void
    {
        foreach ($this->_helpers as $helper) {
            if (!$helper instanceof Helper) {
                continue;
            }
            $helper->run($this);
        }
    }

    /**
     * Render the view.
     *
     * This method is responsible for loading the view files from disk, rendering it and returning it's output.
     *
     * @internal
     */
    public function render(): string
    {
        $output = '';
        $parts = pathinfo($this->_viewfile);
        if ('tpl' == ake($parts, 'extension')) {
            $template = new File\Template\Smarty($this->_viewfile);
            $template->registerFunctionHandler($this);
            $output = $template->render($this->_data);
        } else {
            ob_start();
            if (!($file = $this->getViewFile()) || !file_exists($file)) {
                throw new Exception("View does not exist ({$this->name})", 404);
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
    public function partial(string $view, null|array|bool $data = null, bool $merge_data = false): string
    {
        if (array_key_exists($view, $this->_prepared)) {
            return $this->_prepared[$view];
        }
        /*
         * This converts "absolute paths" to paths that are relative to FILE_PATH_VIEW.
         *
         * Relative paths are then made relative to the current view (using it's absolute path).
         */
        if ('/' === substr($view, 0, 1)) {
            $view = substr($view, 1);
        } else {
            $view = dirname($this->_viewfile).'/'.$view.'.phtml';
        }
        $output = '';
        $partial = new View($view);
        $partial->addHelper($this->_helpers);
        if (is_array($data)) {
            $partial->extend($data);
        } elseif (true === $data) {
            $partial->extend($this->_data);
        }
        $output = $partial->render();
        if (true === $merge_data) {
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
        $this->_prepared[$view] = $content;
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
        $this->_requires_param = array_merge($this->_requires_param, $array);
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
    public function partialLoop($view, array $data): string
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
        return $this->application->url($controller, $action, $params, $absolute);
    }

    /**
     * Returns a date string formatted to the current set date format.
     */
    public function date(Date|string $date): string
    {
        if (!$date instanceof Date) {
            $date = new Date($date);
        }

        return $date->date();
    }

    /**
     * Return a date/time type as a timestamp string.
     *
     * This is for making it quick and easy to output consistent timestamp strings.
     */
    public static function timestamp(Date|string $value): string
    {
        if (!$value instanceof Date) {
            $value = new Date($value);
        }

        return $value->timestamp();
    }

    /**
     * Return a formatted date as a string.
     *
     * @param mixed $value  This can be practically any date type.  Either a \Hazaar\Date object, epoch int, or even a string.
     * @param mixed $format Optionally specify the format to display the date.  Otherwise the current default is used.
     *
     * @return string the nicely formatted datetime string
     */
    public static function datetime($value, $format = null)
    {
        if (!$value instanceof Date) {
            $value = new Date($value);
        }
        if ($format) {
            return $value->format($format);
        }

        return $value->datetime();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->_data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->_data[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        if (null === $offset) {
            $this->_data[] = $value;
        } else {
            $this->_data[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->_data[$offset]);
    }

    /**
     * Use the match/replace algorithm on a string to replace mustache tags with view data.
     *
     * This is similar code used in the Smarty view template renderer.
     *
     * So strings such as:
     *
     * * "Hello, {{entity}}" will replace {{entity}} with the value of `$this->entity`.
     * * "The quick brown {{animal.one}}, jumped over the lazy {{animal.two}}" will replace the tags with values in a multi-dimensional array.
     *
     * @param string $string the string to perform the match/replace on
     *
     * @return string the modified string with mustache tags replaced with view data, or removed if the view data does not exist
     */
    public function matchReplace(string $string): string
    {
        return match_replace($string, $this->_data);
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
        $search_prefixes = ['\\Application\\Helper\\View', '\\Hazaar\\View\\Helper'];
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
