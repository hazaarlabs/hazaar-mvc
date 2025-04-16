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
use Hazaar\Template\Smarty;
use Hazaar\View\FunctionHandler;
use Hazaar\View\Helper;

/**
 * The View class is used to render views in the Hazaar framework.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class View implements \ArrayAccess
{
    /**
     * The application object.
     */
    public Application $application;

    /**
     * Data that is accessible from the view.
     *
     * @var array<mixed>
     */
    protected array $data = [];

    /**
     * View Helpers.
     *
     * @var array<Helper>
     */
    protected array $helpers = [];

    /**
     * Array of function handlers.  These can be either closures or objects that provide public methods.
     *
     * @var array<mixed>
     */
    protected array $functionHandlers = [];

    /**
     * Array of functions that can be called from the view.
     *
     * @var array<string,callable>
     */
    protected array $functions = [];

    protected ?string $viewFile = null;

    /**
     * Array for storing names of initialised helpers so we only initialise them once.
     *
     * @var array<string>
     */
    private array $helpersInit = [];

    /**
     * View constructor.
     *
     * @param string|View  $view     The name of the view to load or a View object to clone
     * @param array<mixed> $viewData The data to pass to the view
     */
    public function __construct(string|View $view, array $viewData = [])
    {
        $this->load($view);
        $this->application = Application::getInstance();
        $this->data = $viewData;
    }

    /**
     * Magic method to get view data.
     */
    public function __get(string $helper): mixed
    {
        return $this->get($helper);
    }

    /**
     * Magic method to set view data.
     *
     * @param string $key   The name of the view data
     * @param mixed  $value The value to set on the view data.  Can be anything including strings, integers, arrays or objects.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Magic method to test if view data is set.
     *
     * @param string $key The name of the view data to look for
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Magic method to remove view data.
     *
     * @param string $key the name of the view data to remove
     */
    public function __unset(string $key): void
    {
        $this->remove($key);
    }

    /**
     * Magic method to call a function from the view.
     *
     * This method will call a function from the view.  The function must be registered with the view using the
     * `registerFunction` or `registerFunctionHandler` methods.
     *
     * @param string       $method The name of the method to call
     * @param array<mixed> $args   The arguments to pass to the method
     */
    public function __call(string $method, array $args): mixed
    {
        if (array_key_exists($method, $this->functions)) {
            return $this->functions[$method](...$args);
        }
        foreach ($this->functionHandlers as $handler) {
            if (method_exists($handler, $method)) {
                return $handler->{$method}(...$args);
            }
        }

        throw new \BadFunctionCallException("Method '{$method}' does not exist in any function handlers.");
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
            $extensions = ['tpl', 'phtml', 'php'];
            foreach ($extensions as $extension) {
                if ($viewfile = Loader::getFilePath($type, $view.'.'.$extension)) {
                    break;
                }
            }
        }

        return $viewfile;
    }

    /**
     * Load a view file.
     *
     * This method will load a view file from disk.  The view file can be either a PHP file or a Smarty template file.
     *
     * @param string $view the name of the view to load
     *
     * @throws \Exception
     */
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
        if ('tpl' == ($parts['extension'] ?? null)) {
            $template = new Smarty();
            $template->loadFromFile(new File($this->viewFile));
            foreach ($this->functions as $name => $function) {
                $template->registerFunction($name, $function);
            }
            foreach ($this->functionHandlers as $handler) {
                $template->registerFunctionHandler($handler);
            }
            $template->registerFunctionHandler(new FunctionHandler($this));
            $output = $template->render(array_merge($this->data, $data ?? []));
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
     * Register a function handler.
     *
     * Function handlers are used to handle custom functions in the view template.  Unlike
     * custom functions, function handlers are objects that provide public methods that can be
     * called from the view template.
     *
     * ### Smarty:
     * ```
     * {$functionName param1="value" param2="value"}
     * ```
     *
     * ### PHP:
     * ```
     * <?php $this->functionName('param1') ?>
     * ```
     *
     * The function will be called with the parameters as an array.  The function must return a string
     * which will be inserted into the view at the point the function was called.
     *
     * @param mixed $handler The function handler object to register
     */
    public function registerFunctionHandler(mixed $handler): void
    {
        $this->functionHandlers[] = $handler;
    }

    /**
     * Register a custom function with the view.
     *
     * Custom functions are functions that can be called from within the view.  The function
     * can be called using the syntax:
     *
     * ### Smarty:
     * ```
     * {$functionName param1="value" param2="value"}
     * ```
     *
     * ### PHP:
     * ```
     * <?php $this->functionName('param1') ?>
     * ```
     *
     * The function will be called with the parameters as an array.  The function must return a string
     * which will be inserted into the view at the point the function was called.
     *
     * @param string   $name     The name of the function to register
     * @param callable $function The function to call when the function is called in the view
     */
    public function registerFunction(string $name, callable $function): void
    {
        $this->functions[$name] = $function;
    }

    /**
     * Sets the function handlers, overwriting any existing handlers.
     *
     * @param array<mixed> $handlers
     *
     * @internal this method is used to set the function handlers from a parent view object
     */
    public function setFunctionHandlers(array $handlers): void
    {
        $this->functionHandlers = $handlers;
    }

    /**
     * Sets the functions, overwriting any existing functions.
     *
     * @param array<string,callable> $functions The functions to set
     *
     * @internal this method is used to set the functions from a parent view object
     */
    public function setFunctions(array $functions): void
    {
        $this->functions = $functions;
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
        $searchPrefixes = ['\Application\Helper\View', '\Hazaar\View\Helper'];
        $name = \ucfirst($name);
        foreach ($searchPrefixes as $prefix) {
            $class = $prefix.'\\'.$name;
            if (\class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}
