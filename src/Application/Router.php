<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Router.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application;

use Hazaar\Controller;
use Hazaar\Loader;

class Router
{
    public bool $isDefaultController = false;

    /**
     * @var array<string, string>
     */
    private array $aliases = [];
    private string $file;
    private string $route;
    private ?string $controller = null;
    private ?string $controller_name = null;
    private bool $useDefaultController = false;

    /**
     * @var null|array<string>|string
     */
    private null|array|string $defaultController = null;

    /**
     * Internal controllers.
     *
     * @var array<string, string>
     */
    public static array $internal = [
        'hazaar' => 'Hazaar\Controller\Internal',
    ];

    public function __construct(Config $config)
    {
        if ($aliases = $config['app']->get('alias')) {
            $this->aliases = $aliases->toArray();
        }
        $this->file = APPLICATION_PATH.DIRECTORY_SEPARATOR.ake($config['app']['files'], 'route', 'route.php');
        $this->useDefaultController = boolify($config['app']['useDefaultController']);
        $this->defaultController = $config['app']['defaultController'];
    }

    public function evaluate(Request $request): bool
    {
        $this->route = $request->getPath();
        if ($this->file && file_exists($this->file)) {
            include $this->file;
        }
        if ($this->route = trim($this->route, '/')) {
            $parts = explode('/', $this->route);
            if ($this->aliases) {
                $match_parts = array_map('strtolower', $parts);
                foreach ($this->aliases as $match => $alias) {
                    $alias_parts = explode('/', strtolower($match));
                    if ($alias_parts !== array_slice($match_parts, 0, count($alias_parts))) {
                        continue;
                    }
                    $leftovers = array_slice($parts, count($alias_parts));
                    $parts = explode('/', $alias);
                    foreach ($parts as &$part) {
                        if ('$' !== $part[0]) {
                            continue;
                        }
                        if ('path' === substr($part, 1)) {
                            $part = implode('/', $leftovers);
                        }
                    }

                    break;
                }
            }
            if (array_key_exists($parts[0], self::$internal)) {
                $this->controller = array_shift($parts);
            } else {
                $this->controller = $this->findController($parts);
            }
            $request->setPath((count($parts) > 0) ? implode('/', $parts) : '');
        } else {
            $this->controller = $this->findController($this->defaultController);
        }
        // If there is no controller and the default controller is active, search for that too.
        if (!$this->controller && true === $this->useDefaultController) {
            $this->controller = $this->findController($this->defaultController);
            $this->controller_name = $request->getFirstPath();
            $this->isDefaultController = true;
        }

        return null !== $this->controller;
    }

    public function getController(): ?string
    {
        return $this->controller;
    }

    public function getControllerName(): ?string
    {
        if ($this->controller_name) {
            return $this->controller_name;
        }

        return $this->controller;
    }

    /**
     * Finds the controller based on the given parts.
     *
     * @param array<string>|string $controller_name the name of the controller
     *
     * @return string the name of the controller
     */
    private function findController(array|string &$controller_name): ?string
    {
        if (!is_array($controller_name)) {
            $controller_name = explode('/', $controller_name);
        }
        $index = 0;
        $controller = null;
        $controller_root = Loader::getFilePath(FILE_PATH_CONTROLLER);
        $controller_path = DIRECTORY_SEPARATOR;
        $controller_index = null;
        foreach ($controller_name as $index => $part) {
            $part = ucfirst($part);
            $found = false;
            $path = $controller_root.$controller_path;
            $controller_path .= $part.DIRECTORY_SEPARATOR;
            if (is_dir($path.$part)) {
                $found = true;
                if (file_exists($controller_root.$controller_path.'Index.php')) {
                    $controller = implode('/', array_slice($controller_name, 0, $index + 1)).'/index';
                    $controller_index = $index;
                }
            }
            if (file_exists($path.$part.'.php')) {
                $found = true;
                $controller = (($index > 0) ? implode('/', array_slice($controller_name, 0, $index)).'/' : null).strtolower($part);
                $controller_index = $index;
            }
            if (false === $found) {
                break;
            }
        }
        if ($controller) {
            $controller_name = array_slice($controller_name, $controller_index + 1);
        }

        return $controller;
    }
}
