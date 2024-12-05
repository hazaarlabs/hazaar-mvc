<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Url.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application;

use Hazaar\Application;

/**
 * @brief       Generate a URL relative to the application
 *
 * @detail      This is the base method for generating URLs in your application.  URLs generated directly from here are
 *              relative to the application base path.  For URLs that are relative to the current controller see
 *              Controller::url()
 *
 *              Parameters are dynamic and depend on what you are trying to generate.
 *
 *              For examples see [Generating URLs](/guide/basics/urls.md) in the
 *              Hazaar MVC support documentation.
 */
class URL implements \JsonSerializable
{
    public string $path = '/';

    /**
     * @var array<mixed>
     */
    public array $params = [];
    public ?string $hash = null;
    public ?string $basePath = null;
    public static ?string $baseURL = '';
    public static bool $rewriteURL = true;

    /**
     * @var array<string>
     */
    public static array $aliases;
    private bool $encoded = false;

    public function __construct()
    {
        if (!func_num_args() > 0) {
            return;
        }
        $parts = [];
        $params = [];
        foreach (\func_get_args() as $part) {
            if (is_array($part) || $part instanceof \stdClass) {
                $params = (array) $part;

                break;
            }
            $part_parts = (false === strpos((string) $part, '/')) ? [$part] : explode('/', (string) $part);
            foreach ($part_parts as $part_part) {
                if (false !== strpos((string) $part_part, '?')) {
                    list($part_part, $part_params) = explode('?', $part_part, 2);
                    parse_str($part_params, $part_params);
                    $params = array_merge($params, $part_params);
                }
                if (!($part_part = trim($part_part ?? ''))) {
                    continue;
                }
                $parts[] = $part_part;
            }
        }
        if (count($parts) > 0) {
            $this->path = implode('/', $parts);
        }
        $this->params = $params;
    }

    /**
     * Returns the string representation of the Url object.
     *
     * @return string the string representation of the Url object
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Initializes the URL class with the provided configuration.
     *
     * @param array<mixed> $config the configuration object containing the base URL, rewrite URL, and default controller
     */
    public static function initialise(array $config): void
    {
        self::$baseURL = $config['base'] ?? '/';
        self::$rewriteURL = $config['rewrite'] ?? '/';
    }

    /**
     * Write the URL as a string.
     *
     * This method optionally takes an array to use to filter any placeholder parameters.  Parameters support special
     * placholder values that are prefixed with a '$', such as $name.  The actual value is then taken from the array
     * supplied to this method and replaced in the output.  This allows a single URL object to be used multiple times
     * and it's parameters changed
     *
     * ## Example:
     *
     * ```php
     * $url = new \Hazaar\Application\Url('controller', 'action', ['id' => '$id']);
     * echo $url->toString(['id' => 1234]);
     * ```
     *
     * This will output something like:
     *
     * ```
     * http://localhost/controller/action?id=1234
     * ```
     *
     * @param null|array<mixed> $values override the default params with parameters in this array
     *
     * @return string the resulting URL based on the constructor arguments
     */
    public function toString(?array $values = null): string
    {
        $params = [];
        foreach ($this->params as $key => $value) {
            if (preg_match('/\$(\w+)/', $value, $matches)) {
                $value = ake($values, $matches[1]);
            }
            $params[$key] = $value;
        }

        return $this->renderObject($params, $this->encoded);
    }

    /**
     * Set the HTTP request parameters on the URL.
     *
     * @param array<mixed> $params
     */
    public function setParams(array $params, bool $merge = false): void
    {
        if (true === $merge) {
            $this->params = array_merge($this->params, $params);
        } else {
            $this->params = $params;
        }
    }

    /**
     * Toggle encoding of the URL.
     *
     * This will enable a feature that will encode the URL with a serialised base64 parameter list so that the path and parameters are obscured.
     *
     * This is NOT a security feature and merely obscures the exact destination of the URL using standard reversible encoding functions that
     * "normal" people won't understand.  It can also make your URL look a bit 'tidier' or 'more professional' by making the parameters
     * look weird. ;)
     *
     * @param bool $encode Boolean to enable/disable encoding.  Defaults to TRUE.
     */
    public function encode(bool $encode = true): self
    {
        $this->encoded = $encode;

        return $this;
    }

    /**
     * Retrieves the origin of the URL.
     *
     * This method extracts the origin from the rendered URL by using a regular expression pattern.
     * The origin is defined as the protocol and domain of the URL.
     *
     * @return string the origin of the URL
     */
    public function getOrigin(): string
    {
        $url = $this->renderObject();
        preg_match('/(\w+\:\/\/[\w\.]+)\//', $url, $matches);

        return $matches[1];
    }

    /**
     * Returns the URL as a JSON serialized string.
     *
     * @return mixed the JSON serialized string representation of the URL
     */
    public function jsonSerialize(): mixed
    {
        return (string) $this;
    }

    /**
     * Write the URL as a string.
     *
     * @param array<mixed> $params override the default params with parameters in this array
     * @param bool         $encode encode the URL as a Hazaar MVC encoded query string URL
     *
     * @return string the resulting URL based on the constructor arguments
     */
    private function renderObject(?array $params = null, bool $encode = false): string
    {
        $path = ($this->basePath ? $this->basePath.'/' : null);
        if (!is_array($params)) {
            $params = [];
        }
        if (URL::$rewriteURL && true !== $encode) {
            $path .= $this->path;
        } elseif ($this->path) {
            $params[Request\HTTP::$pathParam] = $this->path;
        }
        if (count($this->params) > 0) {
            $params = array_merge($this->params, $params);
        }
        if (URL::$baseURL) {
            $url = rtrim(trim(URL::$baseURL), '/').'/'.$path;
        } else {
            // Figure out the hostname and protocol
            $host = ake($_SERVER, 'HTTP_HOST', 'localhost');
            if (false === strpos($host, ':')
                && array_key_exists('SERVER_PORT', $_SERVER)
                && 80 != $_SERVER['SERVER_PORT']
                && 443 != $_SERVER['SERVER_PORT']) {
                $host .= ':'.$_SERVER['SERVER_PORT'];
            }
            $proto = ((443 == ake($_SERVER, 'SERVER_PORT')) ? 'https' : 'http');
            $url = $proto.'://'.$host.Application::getPath($path);
        }
        if (count($params) > 0) {
            $params = http_build_query($params);
            if ($encode) {
                $params = Request\HTTP::$queryParam.'='.base64_encode($params);
            }
            $url .= '?'.$params;
        }
        if ($this->hash) {
            $url .= '#'.$this->hash;
        }

        return $url;
    }
}
