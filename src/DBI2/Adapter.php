<?php
/**
 * @file        Hazaar/DBI/DBI.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\DBI2;

use Hazaar\Application;
use Hazaar\Application\Config;
use Hazaar\DBI2\Exception\DriverNotFound;
use Hazaar\DBI2\Exception\NotConfigured;
use Hazaar\Map;

/**
 * @brief Relational Database Interface
 *
 * @detail The DB Adapter module provides classes for access to relational database via
 * [PDO](http://www.php.net/manual/en/book.pdo.php) (PHP Data Object) drivers and classes. This
 * approach allows developers to use these classes to access a range of different database servers.
 *
 * PDO has supporting drivers for:
 *
 * * [PostgreSQL](http://www.postgresql.org)
 * * [MySQL](http://www.mysql.com)
 * * [SQLite](http://www.sqlite.org)
 * * [MS SQL Server](http://www.microsoft.com/sqlserver)
 * * [Oracle](http://www.oracle.com)
 * * [IBM Informix](http://www.ibm.com/software/data/informix)
 * * [Interbase](http://www.embarcadero.com/products/interbase)
 *
 * Access to database functions is all implemented using a common class structure.  This allows developers
 * to create database queries in code without consideration for the underlying SQL.  SQL is generated
 * "under the hood" using a database specific driver that will automatically take care of any differences
 * in the database servers SQL implementation.
 *
 * ## Example Usage
 *
 * ```php
 * $db = Hazaar\DBI\Adapter::getInstance();
 *
 * $result = $this->execute('SELECT * FROM users');
 *
 * while($row = $result->fetch()){
 *
 * //Do things with $row here
 *
 * }
 * ```
 */
class Adapter
{
    /**
     * @var array<string, int|string>
     */
    public static array $defaultConfig = [
        'encrypt' => [
            'cipher' => 'aes-256-ctr',
            'checkstring' => '!!',
        ],
        'timezone' => 'UTC',
    ];

    public Map $config;

    /**
     * @var array<string,self>
     */
    private static array $instances = [];

    /**
     * @var array<string,Config>
     */
    private static array $loadedConfigs = [];

    private DBD\Interfaces\Driver $driver;

    /**
     * Hazaar DBI Constructor.
     *
     * @param array<mixed>|Map|string $config An array of configuration options to instantiate the DBI Adapter.  This can
     *                                        also be a Hazaar MVC configuration environment if DBI is being used by an HMVC
     *                                        application.
     */
    public function __construct(null|array|Map|string $config = null)
    {
        $configName = null;
        if (defined('HAZAAR_VERSION') && (null === $config || is_string($config))) {
            $configName = $config;
            $config = $this->getDefaultConfig($configName);
        } elseif (!is_string($config)) {
            $config = Map::_($config, self::$defaultConfig);
        } else {
            throw new NotConfigured();
        }
        $this->config = $config;
        if ($configName && !array_key_exists($configName, self::$instances)) {
            self::$instances[$configName] = $this;
        }
        if (!$this->reconfigure()) {
            throw new \Exception('Unkown DBI driver: '.$this->config->get('driver'));
        }
    }

    /**
     * Return an existing or create a new instance of a DBI Adapter.
     *
     * This function should be the main entrypoint to the DBI Adapter class as it implements instance tracking to reduce
     * the number of individual connections to a database.  Normally, instantiating a new DBI Adapter will create a new
     * connection to the database, even if one already exists.  This may be desired so this functionality still exists.
     *
     * However, if using `Hazaar\DBI\Adapter::getInstance()` you will be returned an existing instance if one exists allowing
     * a single connection to be used from multiple sections of code, without the need to pass around an existing reference.
     *
     * NOTE:  Only the first instance created is tracked, so it is still possible to create multiple connections by
     * instantiating the DBI Adapter directly.  The choice is yours.
     */
    public static function getInstance(?string $configEnv = null): self
    {
        if (array_key_exists($configEnv, self::$instances)) {
            return self::$instances[$configEnv];
        }

        return new self($configEnv);
    }

    public function query(string $queryString): false|Result
    {
        return $this->driver->query($queryString);
    }

    public function exec(string $queryString): false|int
    {
        return false;
    }

    public function table(string $tableName): Table
    {
        return new Table($this->driver, $tableName);
    }

    private static function getDefaultConfig(?string &$configName = null): false|Map
    {
        if (!defined('HAZAAR_VERSION')) {
            return false;
        }
        if (!$configName) {
            $configName = APPLICATION_ENV;
        }
        if (!array_key_exists($configName, self::$loadedConfigs)) {
            self::$defaultConfig['timezone'] = date_default_timezone_get();
            if (!Config::$overridePaths) {
                Config::$overridePaths = Application::getConfigOverridePaths();
            }
            $config = new Config('database', $configName, self::$defaultConfig, FILE_PATH_CONFIG, true);
            if (!$config->loaded()) {
                return false;
            }
            self::$loadedConfigs[$configName] = $config;
        }

        return self::$loadedConfigs[$configName];
    }

    private function getDriverClass(string $driver): string
    {
        return 'Hazaar\DBI2\DBD\\'.ucfirst($driver);
    }

    private function reconfigure(bool $reconnect = false): bool
    {
        $driverClass = $this->getDriverClass($this->config->get('driver'));
        if (!class_exists($driverClass)) {
            throw new DriverNotFound($this->config->get('driver'));
        }
        $this->driver = new $driverClass($this->config);

        return true;
    }
}
