<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Config.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

use Hazaar\Exception\ConfigFileNotFound;
use Hazaar\Exception\ConfigParseError;
use Hazaar\Exception\ConfigUnknownFormat;
use Hazaar\Util\Arr;

/**
 * Configuration Class.
 *
 * @detail      The config class loads settings from a configuration file and configures the system ready
 *              for the application to run.  By default the config file used is application.ini and is stored
 *              in the config folder of the application path.
 *
 * @implements  \ArrayAccess<string, mixed>
 * @implements  \Iterator<string, mixed>
 */
class Config implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * @var array<string>
     */
    private array $overridePaths = [];

    /**
     * @var array<mixed>
     */
    private static array $instances = [];
    private string $env;
    private string $basePath = '';
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
     * @param array<mixed> $defaults   initial defaut values9
     */
    public function __construct(
        ?string $sourceFile = null,
        ?string $env = null,
        ?array $defaults = null,
    ) {
        if ($env) {
            $this->setEnvironment($env);
        }
        if ($sourceFile) {
            $this->loadFromFile($sourceFile, $defaults);
        }
    }

    /**
     * Set the environment for the configuration.
     *
     * @param string $env The environment to set
     */
    public function setEnvironment(string $env): void
    {
        $this->env = trim($env);
        if (count($this->global) > 0) {
            $this->loadConfigOptions($this->options, $this->global, $this->env);
        }
    }

    /**
     * Get the config environment that was loaded.
     */
    public function getEnvironment(): string
    {
        return $this->env;
    }

    /**
     * Set the base path for the configuration.
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = DIRECTORY_SEPARATOR.trim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the base path for the configuration.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Set the source file for the configuration.
     *
     * @param array<string> $paths The paths to search for the configuration file overrides
     */
    public function setOverridePaths(array $paths): void
    {
        $this->overridePaths = $paths;
    }

    /**
     * Loads a configuration file and returns the configuration options.
     *
     * @param string       $source   the name of the configuration file to load
     * @param array<mixed> $defaults (optional) The default configuration options
     */
    public function loadFromFile(
        string $source,
        ?array $defaults = null,
    ): bool {
        $this->options = $defaults ?? [];
        $options = [];
        $this->sourceFile = $this->basePath.DIRECTORY_SEPARATOR.ltrim($source, DIRECTORY_SEPARATOR);
        $instanceKey = $this->sourceFile.':'.($this->env ?? '');
        if (array_key_exists($instanceKey, self::$instances)) {
            $this->global = self::$instances[$instanceKey];
            $this->loadConfigOptions($this->options, $this->global, $this->env ?? null);

            return true;
        }
        $sources = [['name' => $this->sourceFile, 'ns' => true]];
        foreach ($this->overridePaths as $override) {
            $sources[] = [
                'name' => $this->basePath.DIRECTORY_SEPARATOR.$override.DIRECTORY_SEPARATOR.$source,
                'ns' => isset($this->env),
            ];
        }
        foreach ($sources as &$sourceInfo) {
            $sourceFile = null;
            $pathInfo = pathinfo($sourceInfo['name']);
            // If we have an extension, just use that file.
            if (false === ($pathInfo['extension'] ?? false)) {
                $extensions = ['json', 'ini']; // Ordered by preference
                foreach ($extensions as $ext) {
                    $filename = $sourceInfo['name'].'.'.$ext;
                    if (file_exists($filename)) {
                        $sourceFile = $filename;

                        break;
                    }
                }
            } elseif (!file_exists($sourceInfo['name'])) {
                continue;
            } else {
                $sourceFile = $sourceInfo['name'];
            }
            // If the file doesn't exist, then skip it.
            if (!$sourceFile) {
                continue;
            }
            $sourceData = $this->loadSourceFile($sourceFile);
            $options[] = (true === $sourceInfo['ns']) ? $sourceData : [$this->env => $sourceData];
        }
        if (0 === count($options)) {
            return false;
        }
        $this->global = [];
        foreach ($options as $o) {
            if (true === ($this->global['final'] ?? false)) {
                break;
            }
            $this->global = array_replace_recursive($this->global, $o);
        }
        $this->loadConfigOptions($this->options, $this->global, $this->env ?? null);
        self::$instances[$instanceKey] = $this->global;

        return true;
    }

    /**
     * Loads the configuration options from an array.
     *
     * @param array<mixed> $options  The configuration options to load
     * @param array<mixed> $defaults (optional) The default configuration options
     */
    public function loadFromArray(array $options, ?array $defaults = null): bool
    {
        if (0 === count($options)) {
            return false;
        }
        $this->options = $defaults ?? [];
        $this->global = array_replace_recursive($this->global, Arr::fromDotNotation($options));
        $this->loadConfigOptions($this->options, $this->global, $this->env ?? null);

        return count($this->options) > 0;
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

        return $this->global[$env] ?? [];
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
        $config = Arr::toDotNotation($this->options);
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

    public function get(string $key): mixed
    {
        return Arr::get($this->options, $key);
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

    public function count(): int
    {
        return count($this->options);
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
                        $mtime = $cache['mtime'] ?? 0;

                        break;
                    }
                }
                if ($mtime > filemtime($source)) {
                    $cacheData = \apcu_fetch($cacheKey);
                    if (is_array($cacheData) && 2 === count($cacheData) && isset($cacheData[0], $cacheData[1])) {
                        [$secureKeys, $source] = \apcu_fetch($cacheKey);
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
        if (!$file->exists()) {
            throw new ConfigFileNotFound($source);
        }
        $extention = $file->extension();
        if ('json' == $extention) {
            if (false === ($config = $file->parseJSON(true))) {
                throw new ConfigParseError($source);
            }
        } elseif ('ini' == $extention) {
            if (!$config = parse_ini_string($file->getContents(), true, INI_SCANNER_TYPED)) {
                throw new ConfigParseError($source);
            }
            foreach ($config as &$array) {
                $array = Arr::fromDotNotation($array);
            }
        } else {
            throw new ConfigUnknownFormat('Unknown file format: '.$source);
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
     * @param array<mixed> $config        the config to store the configuration options
     * @param array<mixed> $globalOptions the global options to load the configuration from
     * @param null|string  $env           The environment to load the configuration for. If null, the default environment will be used.
     */
    private function loadConfigOptions(array &$config, array $globalOptions, ?string $env): void
    {
        $options = null === $env ? $globalOptions : $globalOptions[$env];
        foreach ($options as $key => $values) {
            switch ($key) {
                case 'include':
                    $this->includes = is_array($values) ? $values : [$values];
                    foreach ($this->includes as $includeEnvironment) {
                        $this->loadConfigOptions($config, $globalOptions, $includeEnvironment);
                    }

                    break;

                case 'import':
                    $filesToImport = is_array($values) ? $values : [$values];
                    if (!($importFile = current($filesToImport))) {
                        break;
                    }
                    do {
                        $importEnv = null;
                        if (false !== strpos($importFile, ':')) {
                            [$importFile, $importEnv] = explode(':', $importFile, 2);
                        }
                        $importFile = $this->basePath.DIRECTORY_SEPARATOR.$importFile;
                        $file = realpath($importFile);
                        if (false === $file) {
                            continue;
                        }
                        /*
                         * Check if the file is a directory and append all files in the directory
                         * to the files to import array so that they can be imported.
                         */
                        if (is_dir($file)) {
                            $dirIterator = new \RecursiveDirectoryIterator($file);
                            $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);
                            foreach ($iterator as $importFile) {
                                if ('.' === substr($importFile->getFileName(), 0, 1)) {
                                    continue;
                                }
                                array_push($filesToImport, $importFile->getRealPath().($importEnv ? ':'.$importEnv : ''));
                            }

                            continue;
                        }
                        /*
                         * If the file is not a directory, load the file and merge the options with the
                         * current configuration.
                         */
                        if ($options = $this->loadSourceFile($file)) {
                            if (null === $importEnv && null !== $env) {
                                $options = [$env => $options];
                                $importEnv = $env;
                            }
                            $this->loadConfigOptions($config, $options, $importEnv);
                        }
                    } while ($importFile = next($filesToImport));

                    break;

                default:
                    if (!array_key_exists($key, $config)) {
                        $config[$key] = $values;
                    } else {
                        if (is_array($values)) {
                            $config[$key] = array_merge($config[$key], $values);
                        } else {
                            $config[$key] = $values;
                        }
                    }

                    break;
            }
        }
    }
}
