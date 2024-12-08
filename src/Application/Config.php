<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Config.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
/**
 * The main application entry point.
 */

namespace Hazaar\Application;

use Hazaar\Application;
use Hazaar\File;
use Hazaar\Loader;
use Hazaar\Map;

/**
 * Application Configuration Class.
 *
 * @detail      The config class loads settings from a configuration file and configures the system ready
 *              for the application to run.  By default the config file used is application.ini and is stored
 *              in the config folder of the application path.
 *
 * @implements  \ArrayAccess<string, mixed>
 * @implements  \Iterator<string, mixed>
 */
class Config implements \ArrayAccess, \Iterator
{
    /**
     * @var array<string>
     */
    public static array $overridePaths = [];

    /**
     * @var array<Config>
     */
    private static array $instances = [];
    private string $env;
    private string $source;
    private ?string $sourceFile = null;

    /**
     * @var array<mixed>
     */
    private array $global = [];

    /**
     * @var array<string>
     */
    private array $secureKeys = [];

    /**
     * @var array<string>
     */
    private array $includes = [];

    /**
     * The configuration options for the selected environment.
     *
     * @var array<mixed>
     */
    private array $options = [];

    /**
     * @detail      The application configuration constructor loads the settings from the configuration file specified
     *              in the first parameter.  It will use the second parameter as the starting point and is intended to
     *              allow different operating environments to be configured from a single configuration file.
     *
     * @param string       $sourceFile The absolute path to the config file
     * @param string       $env        The application environment to read settings for.  Usually `development`
     *                                 or `production`.
     * @param array<mixed> $defaults   initial defaut values
     */
    protected function __construct(
        ?string $sourceFile = null,
        ?string $env = null,
        array $defaults = [],
        bool $overrideNamespaces = false
    ) {
        if (!$env) {
            $env = APPLICATION_ENV;
        }
        if (null === $sourceFile || !($this->source = trim($sourceFile))) {
            throw new \Exception('No configuration file specified');
        }
        $this->options = $this->load($this->source, $env, $defaults, $overrideNamespaces);
    }

    /**
     * Output the configuration in a human readable format.
     *
     * @detail      This method is useful for logging, debugging or for using in application administration interfaces
     *              to check the current running configuration.
     *
     *              ### Example Output
     *
     *              <pre>
     *              app.name = Example Application
     *              app.version = 0.0.1
     *              app.layout = application
     *              app.theme.name = test
     *              app.defaultController = Index
     *              app.debug = 1
     *              paths.model = models
     *              paths.view = views
     *              paths.controller = controllers
     *              php.date.timezone = Australia/ACT
     *              php.display_startup_errors = 1
     *              php.display_errors = 1
     *              module.require[] = pgsql
     *              </pre>
     *
     * @return string Config as a multi-line string
     */
    public function __toString(): string
    {
        return $this->tostring();
    }

    /**
     * @detail      The application configuration constructor loads the settings from the configuration file specified
     *              in the first parameter.  It will use the second parameter as the starting point and is intended to
     *              allow different operating environments to be configured from a single configuration file.
     *
     * @param string       $sourceFile The absolute path to the config file
     * @param string       $env        The application environment to read settings for.  Usually `development`
     *                                 or `production`.
     * @param array<mixed> $defaults   initial defaut values
     */
    public static function getInstance(
        ?string $sourceFile = null,
        ?string $env = null,
        array $defaults = [],
        bool $overrideNamespaces = false
    ): Config {
        $sourceKey = $sourceFile.'_'.($env ?? APPLICATION_ENV);
        if (array_key_exists($sourceKey, self::$instances)) {
            return self::$instances[$sourceKey];
        }

        return self::$instances[$sourceKey] = new Config($sourceFile, $env, $defaults, $overrideNamespaces);
    }

    /**
     * Loads a configuration file and returns the configuration options.
     *
     * @param string       $source             the name of the configuration file to load
     * @param string       $env                (optional) The environment to load the configuration for. If null, the default environment will be used.
     * @param array<mixed> $defaults           (optional) The default configuration options
     * @param bool         $overrideNamespaces (optional) Whether to override namespaces when searching for the file
     *
     * @return array<mixed> the configuration options
     */
    public function load(
        string $source,
        string $env = APPLICATION_ENV,
        array $defaults = [],
        bool $overrideNamespaces = false
    ): array {
        $options = [];
        $sources = [['name' => $source, 'ns' => true]];
        $this->env = $env;
        foreach (Config::$overridePaths as $override) {
            $sources[] = ['name' => $override.DIRECTORY_SEPARATOR.$source, 'ns' => $overrideNamespaces];
        }
        foreach ($sources as &$sourceInfo) {
            $sourceFile = null;
            // If we have an extension, just use that file.
            if (false !== ake(pathinfo($sourceInfo['name']), 'extension', false)) {
                $sourceFile = Loader::getFilePath(FILE_PATH_CONFIG, $sourceInfo['name']);
            } else { // Otherwise, search for files with supported extensions
                $extensions = ['json', 'ini']; // Ordered by preference
                foreach ($extensions as $ext) {
                    $filename = $sourceInfo['name'].'.'.$ext;
                    if ($sourceFile = Loader::getFilePath(FILE_PATH_CONFIG, $filename)) {
                        break;
                    }
                }
            }
            // If the file doesn't exist, then skip it.
            if (!$sourceFile) {
                continue;
            }
            $sourceData = $this->loadSourceFile($sourceFile);
            if (null === $this->sourceFile) {
                $this->sourceFile = $sourceFile;
            }
            $options[] = (true === $sourceInfo['ns']) ? $sourceData : [$this->env => $sourceData];
        }
        if (count($options) > 0) {
            $this->global = [];
            foreach ($options as $o) {
                if (true === ake($this->global, 'final')) {
                    break;
                }
                $this->global = array_replace_recursive($this->global, $o);
            }
            $this->loadConfigOptions($defaults, $this->global, $env);
        }

        return $defaults;
    }

    /**
     * Get the config environment that was loaded.
     */
    public function getEnv(): string
    {
        return $this->env;
    }

    /**
     * Retrieves the environment configuration array.
     *
     * This method returns the configuration array for the specified environment.
     * If no environment is specified, it uses the default environment set in the application.
     *
     * @param string $env The environment for which to retrieve the configuration. Defaults to null.
     *
     * @return array<mixed> the configuration array for the specified environment
     */
    public function getEnvConfig(?string $env = null): array
    {
        if (null === $env) {
            $env = $this->env;
        }

        return ake($this->global, $env);
    }

    /**
     * Get the list of environments defined in the configuration.
     *
     * @return array<string> the list of environments
     */
    public function getEnvironments(): array
    {
        return array_keys($this->global);
    }

    /**
     * Converts the configuration object to a string representation.
     *
     * @return string the string representation of the configuration object
     */
    public function toString(): string
    {
        $config = array_to_dot_notation($this->options);
        $output = "[{$this->env}]\n";
        foreach ($config as $key => $value) {
            $output .= "{$key}={$value}\n";
        }

        return $output;
    }

    /**
     * Converts the configuration object to a secure array by removing the secure keys.
     *
     * @return array<mixed> the configuration array without the secure keys
     */
    public function toSecureArray(): array
    {
        return array_diff_key($this->options, array_flip($this->secureKeys));
    }

    public function save(): bool
    {
        if (null === $this->sourceFile) {
            return false;
        }
        $currentData = json_decode(file_get_contents($this->sourceFile), true);
        if (!array_key_exists($this->env, $currentData)) {
            return false;
        }
        $data = array_intersect_key(array_merge(array_combine(array_fill(0, count($this->includes), 'include'), $this->includes), $this->options), $currentData[$this->env]);
        if (array_key_exists('include', $data)) {
            $includes = is_array($data['include']) ? $data['include'] : [$data['include']];

            /**
             * This magic line will return anything that does not already exist in the current data or any
             * of the included config environments.
             */
            $data = array_merge($data, call_user_func_array('array_diff_key', array_values(array_merge([$this->options], $currentData))));
        }
        $currentData[$this->env] = array_merge($currentData[$this->env], $data);

        return file_put_contents($this->sourceFile, json_encode($currentData, JSON_PRETTY_PRINT)) > 0;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->options);
    }

    public function &offsetGet(mixed $offset): mixed
    {
        return $this->options[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->options[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->options[$offset]);
    }

    /**
     * Converts the configuration object to an array.
     *
     * @return array<mixed> the configuration array
     */
    public function toArray(): array
    {
        return $this->options;
    }

    public function current(): mixed
    {
        return current($this->options);
    }

    public function next(): void
    {
        next($this->options);
    }

    public function key(): mixed
    {
        return key($this->options);
    }

    public function valid(): bool
    {
        return null !== key($this->options);
    }

    public function rewind(): void
    {
        reset($this->options);
    }

    /**
     * Extends the configuration options with the given configuration array.
     *
     * This method merges the given configuration array with the existing configuration options.
     *
     * @param array<mixed> $config the configuration array to extend the existing configuration options
     */
    public function extend(array $config): void
    {
        $this->options = array_merge_recursive($this->options, $config);
    }

    /**
     * Loads the configuration file from the given source.
     *
     * @param string $source the path to the configuration file
     *
     * @return array<mixed> the loaded configuration
     *
     * @throws \Exception if there is an error parsing the configuration file or if the file format is unknown
     */
    private function loadSourceFile(string $source): array
    {
        $config = [];
        $cacheKey = null;
        $secureKeys = [];
        // Check if APCu is available for caching and load the config file from cache if it exists.
        if (in_array('apcu', get_loaded_extensions())) {
            $cacheKey = md5(gethostname().':'.$source);
            if (\apcu_exists($cacheKey)) {
                $cacheInfo = \apcu_cache_info();
                $mtime = 0;
                foreach ($cacheInfo['cache_list'] as $cache) {
                    if (array_key_exists('info', $cache) && $cache['info'] == $cacheKey) {
                        $mtime = ake($cache, 'mtime');

                        break;
                    }
                }
                if ($mtime > filemtime($source)) {
                    $cacheData = \apcu_fetch($cacheKey);
                    if (is_array($cacheData) && 2 === count($cacheData) && isset($cacheData[0], $cacheData[1])) {
                        list($secureKeys, $source) = \apcu_fetch($cacheKey);
                    }
                }
            }
        }
        // If we have loaded this config file, continue on to the next
        if ($source && !is_string($source)) {
            $this->secureKeys = array_merge($this->secureKeys, $secureKeys);

            return $source;
        }
        $file = new File($source);
        $extention = $file->extension();
        if ('json' == $extention) {
            if (false === ($config = $file->parseJSON(true))) {
                throw new \Exception('Failed to parse JSON config file: '.$source);
            }
        } elseif ('ini' == $extention) {
            if (!$config = parse_ini_string($file->getContents(), true, INI_SCANNER_TYPED)) {
                throw new \Exception('Failed to parse INI config file: '.$source);
            }
            foreach ($config as &$array) {
                $array = array_from_dot_notation($array);
            }
        } else {
            throw new \Exception('Unknown file format: '.$source);
        }
        if ($file->isEncrypted()) {
            $this->secureKeys = array_merge($this->secureKeys, $this->secureKeys += $secureKeys = array_keys($config));
        }
        // Store the config file in cache
        if (null !== $cacheKey) {
            \apcu_store($cacheKey, [$secureKeys, $config]);
        }

        return $config;
    }

    /**
     * Loads the configuration options into the provided Map object based on the given options array and environment.
     *
     * @param array<mixed> $config  the config to store the configuration options
     * @param array<mixed> $options the array of configuration options
     * @param null|string  $env     The environment to load the configuration for. If null, the default environment will be used.
     */
    private function loadConfigOptions(array &$config, array $options, ?string $env): void
    {
        if (!array_key_exists($env, $options)) {
            return;
        }
        foreach ($options[$env] as $key => $values) {
            if ('include' === $key) {
                $this->includes = is_array($values) ? $values : [$values];
                foreach ($this->includes as $includeEnvironment) {
                    $this->loadConfigOptions($config, $options, $includeEnvironment);
                }
            } elseif ('import' === $key) {
                if (!is_array($values)) {
                    $values = [$values];
                }
                if ($importFile = current($values)) {
                    do {
                        if (!($file = Loader::getFilePath(FILE_PATH_CONFIG, $importFile))) {
                            continue;
                        }
                        if (is_dir($file)) {
                            $dirIterator = new \RecursiveDirectoryIterator($file);
                            $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);
                            foreach ($iterator as $importFile) {
                                if ('.' === substr($importFile->getFileName(), 0, 1)) {
                                    continue;
                                }
                                array_push($values, $importFile->getRealPath());
                            }

                            continue;
                        }
                        if ($options = $this->loadSourceFile($file)) {
                            $config = array_merge_recursive($config, $options);
                        }
                    } while ($importFile = next($values));
                }
            } else {
                if (!array_key_exists($key, $config)) {
                    $config[$key] = $values;
                } else {
                    if (is_array($values)) {
                        $config[$key] = array_merge($config[$key], $values);
                    } else {
                        $config[$key] = $values;
                    }
                }
            }
        }
    }
}
