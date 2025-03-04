<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/Session.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Application;
use Hazaar\Cache\Backend;

/**
 * @brief Session cache backend class
 *
 * @detail The session cache backend class allows access to a PHP session using standard class access methods. It will
 * automatically start a session if one is not already started. Sessions can be namespaced to
 * prevent variable collisions.
 *
 * Available config options:
 *
 * * lifetime - The lifetime to use for cached data. Default: 3600.
 * * session - Any settings to set on the session instance.
 */
class Session extends Backend
{
    protected int $weight = 3;
    private int $timeout = 3600;

    /**
     * @var array<mixed>
     */
    private array $values = [];
    private static bool $started = false;

    private string $baseName = '/';

    public static function available(): bool
    {
        return true;
    }

    /**
     * @detail The session backend init method is responsible for maintaining a valid session.
     * If a session
     * has not already been started it will configure and start one automatically. You can specify
     * a namespace for the session. The constructor will also maintain any timeouts for values
     * as per the application configuration.
     *
     * @param string $namespace
     *                          The namespace to use for this session
     */
    public function init(string $namespace): void
    {
        if (!$namespace) {
            throw new \Exception('Bad session cache namespace!');
        }
        $this->addCapabilities('store_objects', 'array', 'keepalive');
        // Grab the application instance so we can configure the session.
        if (($app = Application::getInstance()) instanceof Application) {
            /*
             * If we have a session name configured in the application config we can set
             * it now for this application. Otherwise just use the default name
             * specified in the PHP configuration. ie: PHPSESSID
             */
            if (isset($app->config['session'])
                && $app->config['session']->has('name')
                && $name = $app->config['session']['name']) {
                session_name($name);
            }
            // Check if we need to configure a session cache expire time.
            if (isset($this->options['lifetime'])) {
                $this->timeout = $this->options['lifetime'];
            } elseif (isset($app->config['session'], $app->config['session']['timeout'])) {
                $this->timeout = (int) $app->config['session']['timeout'];
            }
        }
        // Start the session if we don't already have one
        if (!session_id()) {
            if (true == Session::$started) {
                throw new \Exception('Session already started!');
            }
            /*
             * This is a hack to make sure the session doesn't get cleaned up
             * while we are still using it
             */
            ini_set('session.gc_maxlifetime', $this->timeout * 2);
            ini_set('session.cookie_maxlifetime', $this->timeout * 2);
            session_start();
            Session::$started = true;
        }
        // Check if we have a session timeout
        if (!isset($_SESSION['session']['created'])) {
            $_SESSION['session']['created'] = time();
        } elseif (isset($_SESSION['session']['last_access'])) {
            if ((time() - $_SESSION['session']['last_access']) > $this->timeout) {
                // Reset the session
                $this->clear();
            }
        }
        $_SESSION['session']['last_access'] = time();
        /*
         * If this is the first load the application base won't be an array
         * so we need to set that in the session first.
         */
        if ($app = Application::getInstance()) {
            $this->baseName = $app->getBase();
            if (!array_key_exists($this->baseName, $_SESSION)) {
                $_SESSION[$this->baseName] = [];
            }
            if (!(array_key_exists($this->namespace, $_SESSION[$this->baseName]) && is_array($_SESSION[$this->baseName][$this->namespace]))) {
                $_SESSION[$this->baseName][$this->namespace] = [];
            }
            $this->values = &$_SESSION[$this->baseName][$this->namespace];
        }
    }

    public function close(): bool
    {
        return session_write_close();
    }

    public function has(string $key, bool $check_empty = false): bool
    {
        $value = $this->load($key);

        return "\0" !== $value && '' != $value;
    }

    /**
     * Return the value with key $key, optionally setting a default in the process.
     *
     * If $default is supplied and no value for $key is currently set in the session then
     * the default value will be set in the session and then returned.
     */
    public function &get(string $key): mixed
    {
        $value = $this->load($key);
        if ("\0" === $value) {
            $value = false;
        }

        return $value;
    }

    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        $cache = [
            'data' => $value,
        ];
        if ($timeout > 0) {
            $cache['expire'] = time() + $timeout;
        }
        $this->values[$key] = $cache;

        return true;
    }

    public function remove(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    /**
     * @detail Clears all values from all applications.
     *
     * This equates to a full session reset.
     *
     * @warning Use this wisely as it will affect other applications using the same session.
     */
    public function clear(): bool
    {
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        $_SESSION['session']['created'] = time();
        $_SESSION[$this->baseName][$this->namespace] = [];

        return true;
    }

    public function extend(): void
    {
        $args = func_get_args();
        foreach ($args as $arg) {
            $_SESSION[$this->baseName][$this->namespace] = array_merge($_SESSION[$this->baseName][$this->namespace], $arg);
        }
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $values = [];
        foreach ($_SESSION[$this->baseName][$this->namespace] as $key => $item) {
            if (array_key_exists('expire', $item) && $item['expire'] <= time()) {
                unset($_SESSION[$this->baseName][$this->namespace][$key]);

                continue;
            }
            $values[$key] = $item['data'];
        }

        return $values;
    }

    public function count(): int
    {
        return count($_SESSION[$this->baseName][$this->namespace]);
    }

    private function load(string $key): mixed
    {
        $value = "\0";
        if (array_key_exists($key, $this->values)) {
            $expire = $this->values[$key]['expire'] ?? null;
            if (null !== $expire && $expire < time()) {
                unset($this->values[$key]);
            } else {
                $value = $this->values[$key]['data'] ?? null;
            }
        }

        return $value;
    }
}
