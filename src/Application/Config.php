<?php

declare(strict_types=1);

namespace Hazaar\Application;

use Hazaar\Loader;

class Config extends \Hazaar\Config
{
    /**
     * @var array<string>
     */
    public static array $overridePaths = [];

    /**
     * @var array<string, Config>
     */
    private static array $instances = [];

    protected function __construct(?string $sourceFile = null, ?string $env = null, ?array $defaults = null)
    {
        $configPath = Loader::getFilePath(FilePath::CONFIG);
        if (!$configPath) {
            throw new \RuntimeException('Configuration path not found. Please check your file structure.');
        }
        $this->setBasePath($configPath);
        $this->setEnvironment($env ?? constant('APPLICATION_ENV') ?: 'development');
        if (count(self::$overridePaths) > 0) {
            $this->setOverridePaths(self::$overridePaths);
        }
        parent::__construct($sourceFile, $env, $defaults);
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
        ?array $defaults = null
    ): Config {
        $sourceKey = $sourceFile.'_'.($env ?? constant('APPLICATION_ENV') ?: 'development');
        if (array_key_exists($sourceKey, self::$instances)) {
            return self::$instances[$sourceKey];
        }

        return self::$instances[$sourceKey] = new Config($sourceFile, $env, $defaults);
    }
}
