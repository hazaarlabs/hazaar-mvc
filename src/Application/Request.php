<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Request.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application;

use Exception;

abstract class Request implements Interfaces\Request
{
    protected bool $dispatched = false;

    /**
     * @var array<mixed>
     */
    protected array $params = [];
    protected \Exception $exception;

    /**
     * The original path excluding the application base path.
     */
    protected string $basePath;

    /**
     * The requested path.
     */
    private string $path = '';

    public function __construct()
    {
        $args = func_get_args();
        if (method_exists($this, 'init')) {
            $this->basePath = (string) call_user_func_array([$this, 'init'], $args);
        }
        $this->path = (string) $this->basePath;
    }

    /**
     * Magic method to get the value of a property.
     *
     * @param string $key the name of the property to get
     *
     * @return mixed the value of the property
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Unsets a value from the request object.
     *
     * This method removes a value from the request object using the specified key.
     *
     * @param string $key the key of the value to unset
     */
    public function __unset(string $key): void
    {
        $this->remove($key);
    }

    /**
     * Get the base path of the request.
     *
     * @return string the base path
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Return the request path.
     *
     * @param bool $strip_filename If true, this will cause the function to return anything before the last '/'
     *                             (including the '/') which is the full directory path name. (Similar to dirname()).
     *
     * @return string The path suffix of the request URI
     */
    public function getPath(bool $strip_filename = false): string
    {
        if (true !== $strip_filename) {
            return $this->path;
        }
        $path = ltrim($this->path ?? '', '/');
        if (($pos = strrpos($path, '/')) === false) {
            return '';
        }

        return substr($path, 0, $pos).'/';
    }

    /**
     * Returns the first path segment of the request path.
     *
     * @return string the first path segment
     */
    public function getFirstPath(): string
    {
        if (($pos = strpos($this->path, '/')) === false) {
            return $this->path;
        }

        return substr($this->path, 0, $pos);
    }

    /**
     * Sets the path of the request.
     *
     * @param string $path the path of the request
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * Shift a part off the front of the path.
     *
     * A "part" is simple anything delimited by '/' in the path section of the URL.
     */
    public function shiftPath(): ?string
    {
        if (!$this->path) {
            return null;
        }
        $pos = strpos($this->path, '/');
        if (false === $pos) {
            $part = $this->path;
            $this->path = '';
        } else {
            $part = substr($this->path, 0, $pos);
            $this->path = substr($this->path, $pos + 1);
        }

        return $part;
    }

    /**
     * Prepends a path part to the existing path.
     *
     * @param string $part the path part to prepend
     */
    public function unshiftPath(string $part): void
    {
        $this->path = $part.((strlen($this->path ?? '') > 0) ? '/'.$this->path : '');
    }

    /**
     * Removes and returns the last part of the path.
     *
     * @return string the last part of the path, or null if the path is empty
     */
    public function popPath(): string
    {
        if (!$this->path) {
            return '';
        }
        if (($pos = strrpos($this->path, '/')) === false) {
            $part = $this->path;
            $this->path = '';
        } else {
            $part = substr($this->path, $pos + 1);
            $this->path = substr($this->path, 0, $pos);
        }

        return $part;
    }

    /**
     * Appends a path part to the existing request path.
     *
     * @param string $part the path part to append
     */
    public function pushPath(string $part): void
    {
        $this->path .= ((strlen($this->path) > 0) ? '/' : '').$part;
    }

    /**
     * Retrieve a request value.
     *
     * These values can be sent in a number of ways.
     * * In a query string.  eg: http://youhost.com/controller?key=value
     * * As form POST data.
     * * As JSON encoded request body.
     *
     * Only JSON encoded request bodies support data typing.  All other request values will be
     * strings.
     *
     * @param string $key     The data key to retrieve
     * @param mixed  $default if the value is not set, use this default value
     *
     * @return mixed most of the time this will return a string, unless data-typing is available when using JSON requests
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (($value = ake($this->params, $key)) !== null) {
            if ('null' === $value) {
                $value = null;
            } elseif ('true' == $value || 'false' == $value) {
                $value = boolify($value);
            }

            return $value;
        }

        return $default;
    }

    /**
     * Retrieve an integer value from the request.
     *
     * The most common requests will not provide data typing and data value will always be a string.  This method
     * will automatically return the requested value as an integer unless it is NULL or not set.  In which case
     * either NULL or the default value will be returned.
     *
     * @param string $key     the key of the request value to return
     * @param int    $default a default value to use if the value is NULL or not set
     */
    public function getInt(string $key, ?int $default = null): ?int
    {
        return $this->get($key, $default);
    }

    /**
     * Retrieve an float value from the request.
     *
     * The most common requests will not provide data typing and data value will always be a string.  This method
     * will automatically return the requested value as an float unless it is NULL or not set.  In which case
     * either NULL or the default value will be returned.
     *
     * @param string $key     the key of the request value to return
     * @param float  $default a default value to use if the value is NULL or not set
     */
    public function getFloat(string $key, ?float $default = null): float
    {
        return $this->get($key, $default);
    }

    /**
     * Retrieve an boolean value from the request.
     *
     * The most common requests will not provide data typing and data value will always be a string.  This method
     * will automatically return the requested value as an boolean unless it is NULL or not set.  In which case
     * either NULL or the default value will be returned.
     *
     * This internally uses the boolify() function so the usual bool strings are supported (t, f, true, false, 0, 1, on, off, etc).
     *
     * @param string $key     the key of the request value to return
     * @param bool   $default a default value to use if the value is NULL or not set
     */
    public function getBool($key, $default = null): bool
    {
        return boolify($this->get($key, $default));
    }

    /**
     * Check to see if a request value has been set.
     *
     * @param array<string>|string $keys      the key of the request value to check for
     * @param bool                 $check_any The check type when $key is an array.  TRUE means that ANY key must exist.  FALSE means ALL keys must exist.
     *
     * @return bool true if the value is set, False otherwise
     */
    public function has(array|string $keys, bool $check_any = false): bool
    {
        // If the parameter is an array, make sure all of the keys exist before returning true
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        $result = false;
        $count = count(array_intersect($keys, array_keys($this->params)));

        return $check_any ? $count > 0 : $count === count($keys);
    }

    /**
     * Set a request value.
     *
     * This would not normally be used and has no internal implications on how the application will function
     * as this data is not processed in any way.  However setting request data may be useful in your application
     * when reusing/repurposing controller actions so that they may be called from somewhere else in your
     * application.
     *
     * @param string $key   the key value to set
     * @param mixed  $value the new value
     */
    public function set(string $key, mixed $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * Removes a parameter from the request.
     *
     * @param string $key the key of the parameter to remove
     */
    public function remove(string $key): void
    {
        unset($this->params[$key]);
    }

    /**
     * Return an array of request parameters as key/value pairs.
     *
     * @param array<string> $filter_in  only include parameters with keys specified in this filter
     * @param array<string> $filter_out exclude parameters with keys specified in this filter
     *
     * @return array<mixed> the request parameters
     */
    public function getParams(?array $filter_in = null, ?array $filter_out = null): array
    {
        if (null === $filter_in && null === $filter_out) {
            return $this->params;
        }
        $params = $this->params;
        if ($filter_in) {
            if (!is_array($filter_in)) {
                $filter_in = [$filter_in];
            }
            $params = array_intersect_key($params, array_flip($filter_in));
        }
        if ($filter_out) {
            if (!is_array($filter_out)) {
                $filter_out = [$filter_out];
            }
            $params = array_diff_key($params, array_flip($filter_out));
        }

        return $params;
    }

    /**
     * Check if the request has any parameters.
     *
     * @return bool returns true if the request has parameters, false otherwise
     */
    public function hasParams(): bool
    {
        return count($this->params) > 0;
    }

    /**
     * Sets the parameters of the request.
     *
     * @param array<mixed> $array The array of parameters to set
     */
    public function setParams(array $array): void
    {
        $this->params = array_merge($this->params, $array);
        foreach ($this->params as $key => $value) {
            if ('amp;' == substr($key, 0, 4)) {
                $newKey = substr($key, 4);
                $this->params[$newKey] = $value;
                unset($this->params[$key]);
            }
        }
    }

    /**
     * Returns the number of parameters in the request.
     *
     * @return int the number of parameters in the request
     */
    public function count(): int
    {
        return count($this->params);
    }

    /**
     * Sets the dispatched flag for the request.
     *
     * @param bool $flag the value to set for the dispatched flag
     */
    public function setDispatched(bool $flag = true): void
    {
        $this->dispatched = $flag;
    }

    /**
     * Checks if the request has been dispatched.
     *
     * @return bool true if the request has been dispatched, false otherwise
     */
    public function isDispatched(): bool
    {
        return $this->dispatched;
    }

    /**
     * Sets the exception for the request.
     *
     * @param \Exception $e the exception to set
     */
    public function setException(\Exception $e): void
    {
        $this->exception = $e;
    }

    /**
     * Checks if the request has an exception.
     *
     * @return bool true if the request has an exception, false otherwise
     */
    public function hasException(): bool
    {
        return $this->exception instanceof \Exception;
    }

    /**
     * Gets the exception associated with the request.
     *
     * @return null|\Exception the exception associated with the request, or null if there is no exception
     */
    public function getException(): ?\Exception
    {
        return $this->exception;
    }
}
