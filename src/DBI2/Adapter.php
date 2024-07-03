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
use Hazaar\DBI2\Interfaces\QueryBuilder;
use Hazaar\DBI2\Schema\Manager;
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
 *
 * @method false|int    exec(string $sql)
 * @method false|Result query(string $sql)
 * @method false|string quote(mixed $string, int $type = \PDO::PARAM_STR)
 * @method bool         setTimezone(string $tz)
 * @method array|false  errorInfo()
 * @method string       errorCode()
 * @method QueryBuilder getQueryBuilder()
 * @method bool         repair()
 *
 * TRANSACTION
 * @method bool begin()
 * @method bool commit()
 * @method bool cancel()
 *
 * SCHEMA
 * @method string getSchemaName()
 * @method bool   schemaExists(?string $schemaName = null)
 * @method bool   createSchema(string $schemaName)
 *
 * ROLES
 * @method array       listRoles()
 * @method bool        createRole(string $roleName, ?string $password = null)
 * @method bool        dropRole(string $roleName)
 * @method bool        grant(array|string $role, string $to, string $on)
 * @method bool        revoke(array|string $role, string $from, string $on)
 *                                                                                                                           TABLES                                                                                                                     TABLES
 * @method array       listTables()
 * @method bool        createTable(string $tableName, mixed $columns)
 * @method array|false describeTable(string $tableName, ?string $sort = null)
 * @method bool        renameTable(string $fromName, string $toName)
 * @method bool        dropTable(string $name, bool $cascade = false, bool $ifExists = false)
 * @method bool        addColumn(string $tableName, mixed $columnSpec)
 * @method bool        alterColumn(string $tableName, string $column, mixed $columnSpec)
 * @method bool        dropColumn(string $tableName, string $column, bool $ifExists = false)
 * @method bool        truncate(string $tableName, bool $only = false, bool $restartIdentity = false, bool $cascade = false)
 *
 * VIEWS
 * @method array       listViews()
 * @method array|false describeView($name)
 * @method bool        createView(string $name, mixed $content)
 * @method bool        viewExists(string $viewName)
 * @method bool        dropView(string $name, bool $cascade = false, bool $ifExists = false)
 *
 * INDEXES
 * @method array listIndexes()
 * @method bool  createIndex(string $indexName, string $tableName, mixed $idxInfo)
 * @method bool  dropIndex(string $indexName, bool $ifExists = false)
 *
 * CONSTRAINTS
 * @method array       listConstraints()
 * @method array|false listConstraints($table = null, $type = null, $invertType = false)
 * @method bool        addConstraint(string $constraintName, mixed $info)
 * @method bool        dropConstraint(string $constraintName, string $tableName, bool $cascade = false, bool $ifExists = false)
 *
 * EXTENSIONS
 * @method array listExtensions()
 * @method bool  createExtension(string $name)
 * @method bool  dropExtension(string $name, bool $ifExists = false)
 *
 * TRIGGERS
 * @method array       listTriggers()
 * @method array|false describeTrigger(string $triggerName, ?string $schemaName = null)
 * @method bool        createTrigger(string $triggerName, string $tableName, mixed $spec = [])
 * @method bool        dropTrigger(string $triggerName, string $tableName, bool $cascade = false, bool $ifExists = false)
 *
 * SEQUENCES
 * @method array       listSequences()
 * @method array|false describeSequence(string $name)
 * @method bool        createSequence(string $name, int $start = 1, int $increment = 1)
 * @method bool        dropSequence(string $name, bool $ifExists = false)
 * @method false|int   nextSequenceValue(string $name)
 * @method bool        setSequenceValue(string $name, int $value)
 *
 * FUNCTIONS
 * @method array       listFunctions()
 * @method array|false describeFunction(string $name, ?string $schemaName = null)
 * @method bool        createFunction($name, $spec)
 * @method bool        dropFunction(string $name, null|array|string $argTypes = null, bool $cascade = false, bool $ifExists = false)
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

    private Manager $schemaManager;

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
        }
        if (!$config) {
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
     * Magic method to pass any undefined methods to the driver.
     *
     * This allows the DBI Adapter to be used as a proxy to the underlying driver.
     *
     * @param string       $method The method name to call
     * @param array<mixed> $args   The arguments to pass to the method
     *
     * @return mixed The result of the method call
     */
    public function __call(string $method, array $args): mixed
    {
        if (!method_exists($this->driver, $method)) {
            throw new \Exception('Method '.$method.' does not exist in '.get_class($this->driver).' driver.');
        }

        return call_user_func_array([$this->driver, $method], $args);
    }

    public function can(string $method): bool
    {
        return method_exists($this->driver, $method);
    }

    public function getDriverName(): string
    {
        return strtoupper(get_class($this->driver));
    }

    /**
     * Returns an instance of the Hazaar\DBI\Schema\Manager for managing database schema versions.
     */
    public function getSchemaManager(): Manager
    {
        if (!isset($this->schemaManager)) {
            $this->schemaManager = new Manager($this->config);
        }

        return $this->schemaManager;
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

    public function errorException(?string $msg = null): \Exception
    {
        if ($err = $this->errorInfo()) {
            if (null !== $err[1]) {
                $msg .= ((null !== $msg) ? ' SQL ERROR '.$err[0].': ' : $err[0].': ').$err[2];
            }
            if (null !== $msg) {
                return new \Exception($msg, (int) $err[1]);
            }
        }

        return new \Exception('Unknown DBI Error!');
    }

    /**
     * Insert a new record into the specified table.
     *
     * @param string $tableName the name of the table to insert the record into
     * @param mixed  $data      The data to be inserted. This can be an associative array or an object.
     * @param mixed  $returning Optional. Whether to return the inserted record. Defaults to false.
     *
     * @return mixed returns false if the insert fails, otherwise returns the ID of the inserted record
     */
    public function insert(string $tableName, mixed $data, mixed $returning = null): mixed
    {
        return $this->table($tableName)->insert($data, $returning);
    }

    /**
     * Update a record in the specified table.
     *
     * @param string $tableName the name of the table to update the record in
     * @param mixed  $data      The data to be updated. This can be an associative array or an object.
     * @param mixed  $where     The WHERE clause to apply to the update. This can be an associative array or an object.
     * @param mixed  $returning Optional. Whether to return the updated record. Defaults to false.
     *
     * @return false|int returns false if the update fails, otherwise returns the number of rows updated
     */
    public function update(string $tableName, mixed $data, mixed $where, mixed $returning = null): false|int
    {
        return $this->table($tableName)->update($data, $where, $returning);
    }

    /**
     * Delete a record from the specified table.
     *
     * @param string $tableName the name of the table to delete the record from
     * @param mixed  $where     The WHERE clause to apply to the delete. This can be an associative array or an object.
     *
     * @return false|int returns false if the delete fails, otherwise returns the number of rows deleted
     */
    public function delete(string $tableName, mixed $where): false|int
    {
        return $this->table($tableName)->delete($where);
    }

    /**
     * Returns a Table object for the specified table name.
     *
     * @param string $tableName the name of the table
     *
     * @return Table the Table object for the specified table name
     */
    public function table(string $tableName, ?string $alias = null): Table
    {
        return new Table($this, $tableName, $alias);
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
