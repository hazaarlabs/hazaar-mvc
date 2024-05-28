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
use Hazaar\Exception;
use Hazaar\File;
use Hazaar\Loader;
use Hazaar\Map;

/**
 * Application Configuration Class.
 *
 * @detail      The config class loads settings from a configuration file and configures the system ready
 *              for the application to run.  By default the config file used is application.ini and is stored
 *              in the config folder of the application path.
 */
class Config extends Map
{
    /**
     * @var array<string>
     */
    public static array $overridePaths = [];
    private string $env;
    private string $source;
    private ?string $sourceFile = null;

    /**
     * @var array<mixed>
     */
    private array $global = [];
    private bool $loaded = false;

    /**
     * @var array<string>
     */
    private array $secureKeys = [];

    /**
     * @var array<string>
     */
    private array $includes = [];

    /**
     * @detail      The application configuration constructor loads the settings from the configuration file specified
     *              in the first parameter.  It will use the second parameter as the starting point and is intended to
     *              allow different operating environments to be configured from a single configuration file.
     *
     * @param string       $sourceFile The absolute path to the config file
     * @param string       $env        The application environment to read settings for.  Usually `development`
     *                                 or `production`.
     * @param array<mixed> $defaults   initial defaut values
     * @param string       $pathType   the search path type to look for configuration files
     */
    public function __construct(
        ?string $sourceFile = null,
        ?string $env = null,
        array $defaults = [],
        string $pathType = FILE_PATH_CONFIG,
        bool $overrideNamespaces = false
    ) {
        $config = null;
        if (!$env) {
            $env = APPLICATION_ENV;
        }
        $this->env = $env;
        if (null !== $sourceFile && ($this->source = trim($sourceFile))) {
            if ($config = $this->load($this->source, $defaults, $pathType, Config::$overridePaths, $overrideNamespaces)) {
                $this->loaded = ($config->count() > 0);
            } else {
                $config = $defaults;
            }
        }
        $filters = [
            'out' => [
                [
                    'callback' => [$this, 'parseString'],
                ],
            ],
            'in' => [
                [
                    'callback' => function ($value) {
                        if ('true' === $value) {
                            return true;
                        }
                        if ('false' === $value) {
                            return false;
                        }

                        return $value;
                    },
                ],
            ],
        ];
        parent::__construct($config, null, $filters);
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
     * Loads a configuration file and returns the configuration options.
     *
     * @param string        $source             the name of the configuration file to load
     * @param array<mixed>  $defaults           (optional) The default configuration options
     * @param string        $pathType           (optional) The type of path to use for loading the file
     * @param array<string> $overridePaths      (optional) An array of override paths to search for the file
     * @param bool          $overrideNamespaces (optional) Whether to override namespaces when searching for the file
     *
     * @return bool|Map returns a Map object containing the configuration options, or false if the file could not be loaded
     */
    public function load(
        string $source,
        array $defaults = [],
        string $pathType = FILE_PATH_CONFIG,
        array $overridePaths = [],
        bool $overrideNamespaces = false
    ): bool|Map {
        $options = [];
        $sources = [['name' => $source, 'ns' => true]];
        if ($overridePaths) {
            if (!is_array($overridePaths)) {
                $overridePaths = [$overridePaths];
            }
            foreach ($overridePaths as $override) {
                $sources[] = ['name' => $override.DIRECTORY_SEPARATOR.$source, 'ns' => $overrideNamespaces];
            }
        }
        foreach ($sources as &$sourceInfo) {
            $sourceFile = null;
            // If we have an extension, just use that file.
            if (false !== ake(pathinfo($sourceInfo['name']), 'extension', false)) {
                $sourceFile = Loader::getFilePath($pathType, $sourceInfo['name']);
            } else { // Otherwise, search for files with supported extensions
                $extensions = ['json', 'ini']; // Ordered by preference
                foreach ($extensions as $ext) {
                    $filename = $sourceInfo['name'].'.'.$ext;
                    if ($sourceFile = Loader::getFilePath($pathType, $filename)) {
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
        if (!count($options) > 0) {
            return false;
        }
        $combined = [];
        foreach ($options as $o) {
            if (true === ake($combined, 'final')) {
                break;
            }
            $combined = array_replace_recursive($combined, $o);
        }
        $config = new Map($defaults);
        if (!$this->loadConfigOptions($combined, $config)) {
            return false;
        }

        return $config;
    }

    /**
     * Check whether the config was loaded from the source file.
     */
    public function loaded(): bool
    {
        return $this->loaded;
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
    public function tostring(): string
    {
        $config = $this->todotnotation();
        $output = "[{$this->env}]\n";
        foreach ($config as $key => $value) {
            $output .= "{$key}={$value}\n";
        }

        return $output;
    }

    /**
     * Parses a string by replacing placeholders with their corresponding values.
     *
     * @param mixed  $elem the string to be parsed
     * @param string $key  the key used for parsing the string
     *
     * @return mixed the parsed string
     */
    public function parseString(mixed $elem, string $key): mixed
    {
        $allowedValues = [
            'GLOBALS' => $GLOBALS,
            '_SERVER' => &$_SERVER,
            '_GET' => &$_GET,
            '_POST' => &$_POST,
            '_FILES' => &$_FILES,
            '_COOKIE' => &$_COOKIE,
            '_SESSION' => &$_SESSION,
            '_REQUEST' => &$_REQUEST,
            '_ENV' => &$_ENV,
        ];
        if ($app = Application::getInstance()) {
            $allowedValues['_APP'] = &$app->GLOBALS;
        }
        if (is_string($elem) && preg_match_all('/%([\w\[\]]*)%/', $elem, $matches)) {
            foreach ($matches[0] as $index => $match) {
                if (false !== strpos($matches[1][$index], '[')) {
                    parse_str($matches[1][$index], $result);
                    $parts = explode('.', key(array_to_dot_notation($result)));
                    if (!array_key_exists($parts[0], $allowedValues)) {
                        return '';
                    }
                    $value = &$allowedValues;
                    foreach ($parts as $part) {
                        if (!($value && array_key_exists($part, $value))) {
                            return '';
                        }
                        $value = &$value[$part];
                    }
                } else {
                    $value = defined($matches[1][$index]) ? constant($matches[1][$index]) : '';
                }
                $elem = preg_replace('/'.preg_quote($match).'/', $value, $elem, 1);
            }
        }

        return $elem;
    }

    /**
     * Converts the configuration object to a secure array by removing the secure keys.
     *
     * @return array<mixed> the configuration array without the secure keys
     */
    public function toSecureArray(): array
    {
        $config = parent::toArray(false);

        return array_diff_key($config, array_flip($this->secureKeys));
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
        $configData = $this->toArray(false, false);
        $data = array_intersect_key(array_merge(array_combine(array_fill(0, count($this->includes), 'include'), $this->includes), $configData), $currentData[$this->env]);
        if (array_key_exists('include', $data)) {
            $includes = is_array($data['include']) ? $data['include'] : [$data['include']];

            /**
             * This magic line will return anything that does not already exist in the current data or any
             * of the included config environments.
             */
            $data = array_merge($data, call_user_func_array('array_diff_key', array_values(array_merge([$configData], $currentData))));
        }
        $currentData[$this->env] = array_merge($currentData[$this->env], $data);

        return file_put_contents($this->sourceFile, json_encode($currentData, JSON_PRETTY_PRINT)) > 0;
    }

    /**
     * Loads the configuration file from the given source.
     *
     * @param string $source the path to the configuration file
     *
     * @return array<mixed> the loaded configuration
     *
     * @throws Exception if there is an error parsing the configuration file or if the file format is unknown
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
                throw new Exception('Failed to parse JSON config file: '.$source);
            }
        } elseif ('ini' == $extention) {
            if (!$config = parse_ini_string($file->getContents(), true, INI_SCANNER_TYPED)) {
                throw new Exception('Failed to parse INI config file: '.$source);
            }
            foreach ($config as &$array) {
                $array = array_from_dot_notation($array);
            }
        } else {
            throw new Exception('Unknown file format: '.$source);
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
     * @param array<mixed> $options the array of configuration options
     * @param Map          $config  the Map object to store the configuration options
     * @param null|string  $env     The environment to load the configuration for. If null, the default environment will be used.
     *
     * @return bool returns true if the configuration options were loaded successfully, false otherwise
     */
    private function loadConfigOptions(array $options, Map $config, ?string $env = null): bool|Map
    {
        if (!$env) {
            $env = $this->env;
        }
        if (!array_key_exists($env, $options)) {
            return false;
        }
        $this->global = array_merge($this->global, $options);
        foreach ($options[$env] as $key => $values) {
            if ('include' === $key) {
                $this->includes = is_array($values) ? $values : [$values];
                foreach ($this->includes as $includeEnvironment) {
                    $this->loadConfigOptions($options, $config, $includeEnvironment);
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
                            $config->extend($options);
                        }
                    } while ($importFile = next($values));
                }
            } else {
                $config->set($key, $values, true);
            }
        }

        return $config;
    }
}
