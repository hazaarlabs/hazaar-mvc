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

    /**
     * @return array{string, string, string}
     */
    public function errorInfo(): array|false
    {
        return $this->driver->errorInfo();
    }

    public function errorCode(): string
    {
        return $this->driver->errorCode();
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

    public function getSchemaName(): string
    {
        return $this->driver->getSchemaName();
    }

    public function schemaExists(?string $schemaName = null): bool
    {
        return $this->driver->schemaExists($schemaName);
    }

    public function createSchema(string $name): bool
    {
        return $this->driver->createSchema($name);
    }

    /**
     * @param array<string>|string $privilege
     */
    public function grant(array|string $privilege, string $object, string $to, ?string $schema = null): bool
    {
        return $this->driver->grant($privilege, $object, $to, $schema);
    }

    /**
     * @param array<string>|string $privilege
     */
    public function revoke(array|string $privilege, string $object, string $from, ?string $schema = null): bool
    {
        return $this->driver->revoke($privilege, $object, $from, $schema);
    }

    /**
     * Begins a database transaction.
     *
     * @return bool returns true if the transaction was successfully started, false otherwise
     */
    public function begin(): bool
    {
        return $this->driver->begin();
    }

    /**
     * Cancels the current database transaction and rolls back any changes made.
     *
     * @return bool returns true if the transaction was successfully canceled and rolled back, false otherwise
     */
    public function cancel(): bool
    {
        return $this->driver->cancel();
    }

    /**
     * Commits the current database transaction.
     *
     * @return bool returns `true` if the transaction was successfully committed, `false` otherwise
     */
    public function commit(): bool
    {
        return $this->driver->commit();
    }

    /**
     * Executes a database query.
     *
     * @param string $queryString the SQL query string to execute
     *
     * @return false|Result returns a Result object if the query is successful, otherwise returns false
     */
    public function query(string $queryString): false|Result
    {
        return $this->driver->query($queryString);
    }

    /**
     * Executes a database query.
     *
     * @param string $queryString the SQL query string to execute
     *
     * @return false|int returns the number of affected rows on success, or false on failure
     */
    public function exec(string $queryString): false|int
    {
        return $this->driver->exec($queryString);
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
        return new Table($this->driver, $tableName, $alias);
    }

    /**
     * @return array<array{name:string,schema:string}>
     */
    public function listTables(): array
    {
        return $this->driver->listTables();
    }

    public function createTable(string $tableName, mixed $columns): bool
    {
        return $this->driver->createTable($tableName, $columns);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeTable(string $tableName, ?string $sort = null): array|false
    {
        return $this->driver->describeTable($tableName, $sort);
    }

    public function renameTable(string $fromName, string $toName): bool
    {
        return $this->driver->renameTable($fromName, $toName);
    }

    public function dropTable(string $name, bool $cascade = false, bool $ifExists = false): bool
    {
        return $this->driver->dropTable($name, $cascade, $ifExists);
    }

    public function addColumn(string $tableName, mixed $columnSpec): bool
    {
        return $this->driver->addColumn($tableName, $columnSpec);
    }

    public function alterColumn(string $tableName, string $column, mixed $columnSpec): bool
    {
        return $this->driver->alterColumn($tableName, $column, $columnSpec);
    }

    public function dropColumn(string $tableName, string $column, bool $ifExists = false): bool
    {
        return $this->driver->dropColumn($tableName, $column, $ifExists);
    }

    /**
     * @return array<string>
     */
    public function listSequences(): array
    {
        return $this->driver->listSequences();
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeSequence(string $name): array|false
    {
        return $this->driver->describeSequence($name);
    }

    /**
     * @return array<string,array{table:string,columns:array<string>,unique:bool}>
     */
    public function listIndexes(?string $table = null): array
    {
        return $this->driver->listIndexes($table);
    }

    public function createIndex(string $indexName, string $tableName, mixed $idxInfo): bool
    {
        return $this->driver->createIndex($indexName, $tableName, $idxInfo);
    }

    public function dropIndex(string $indexName, bool $ifExists = false): bool
    {
        return $this->driver->dropIndex($indexName, $ifExists);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listConstraints(
        ?string $tableName = null,
        ?string $type = null,
        bool $invertType = false
    ): array|false {
        return $this->driver->listConstraints($tableName, $type, $invertType);
    }

    public function addConstraint(string $constraintName, mixed $info): bool
    {
        return $this->driver->addConstraint($constraintName, $info);
    }

    public function dropConstraint(string $constraintName, string $tableName, bool $cascade = false, bool $ifExists = false): bool
    {
        return $this->driver->dropConstraint($constraintName, $tableName, $cascade, $ifExists);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listViews(): array|false
    {
        return $this->driver->listViews();
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeView(string $name): array|false
    {
        return $this->driver->describeView($name);
    }

    public function createView(string $name, mixed $content): bool
    {
        return $this->driver->createView($name, $content);
    }

    public function viewExists(string $viewName): bool
    {
        return $this->driver->viewExists($viewName);
    }

    public function dropView(string $name, bool $cascade = false, bool $ifExists = false): bool
    {
        return $this->driver->dropView($name, $cascade, $ifExists);
    }

    /**
     * List defined functions.
     *
     * @return array<int,array<mixed>|string>|false
     */
    public function listFunctions(?string $schemaName = null, bool $includeParameters = false): array|false
    {
        return $this->driver->listFunctions($schemaName, $includeParameters);
    }

    /**
     * @return array<int,array{
     *  name:string,
     *  return_type:string,
     *  content:string,
     *  parameters:?array<int,array{name:string,type:string,mode:string,ordinal_position:int}>,
     *  lang:string
     * }>|false
     */
    public function describeFunction(string $name, ?string $schemaName = null): array|false
    {
        return $this->driver->describeFunction($name, $schemaName);
    }

    /**
     * Create a new database function.
     *
     * @param mixed $name The name of the function to create
     * @param mixed $spec A function specification.  This is basically the array returned from describeFunction()
     *
     * @return bool
     */
    public function createFunction($name, $spec)
    {
        return $this->driver->createFunction($name, $spec);
    }

    /**
     * Remove a function from the database.
     *
     * @param string                    $name     The name of the function to remove
     * @param null|array<string>|string $argTypes the argument list of the function to remove
     * @param bool                      $cascade  Whether to perform a DROP CASCADE
     */
    public function dropFunction(
        string $name,
        null|array|string $argTypes = null,
        bool $cascade = false,
        bool $ifExists = false
    ): bool {
        return $this->driver->dropFunction($name, $argTypes, $cascade, $ifExists);
    }

    /**
     * List defined triggers.
     *
     * @param string $schemaName Optional: schema name.  If not supplied the current schemaName is used.
     *
     * @return array<int,array{schema:string,name:string}>|false
     */
    public function listTriggers(?string $tableName = null, ?string $schemaName = null): array|false
    {
        return $this->driver->listTriggers($tableName, $schemaName);
    }

    /**
     * Describe a database trigger.
     *
     * This will return an array as there can be multiple triggers with the same name but with different attributes
     *
     * @param string $schemaName Optional: schemaName name.  If not supplied the current schemaName is used.
     *
     * @return array{
     *  name:string,
     *  events:array<string>,
     *  table:string,
     *  content:string,
     *  orientation:string,
     *  timing:string
     * }|false
     */
    public function describeTrigger(string $triggerName, ?string $schemaName = null): array|false
    {
        return $this->driver->describeTrigger($triggerName, $schemaName);
    }

    /**
     * Summary of createTrigger.
     *
     * @param string $tableName The table on which the trigger is being created
     * @param mixed  $spec      The spec of the trigger.  Basically this is the array returned from describeTriggers()
     */
    public function createTrigger(string $triggerName, string $tableName, mixed $spec = []): bool
    {
        return $this->driver->createTrigger($triggerName, $tableName, $spec);
    }

    /**
     * Drop a trigger from a table.
     *
     * @param string $tableName The name of the table to remove the trigger from
     * @param bool   $cascade   Whether to drop CASCADE
     */
    public function dropTrigger(string $triggerName, string $tableName, bool $cascade = false, bool $ifExists = false): bool
    {
        return $this->driver->dropTrigger($triggerName, $tableName, $cascade, $ifExists);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listUsers(): array|false
    {
        return $this->driver->listUsers();
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listGroups(): array|false
    {
        return $this->driver->listGroups();
    }

    /**
     * @param array<string> $privileges
     */
    public function createRole(string $name, ?string $password = null, array $privileges = []): bool
    {
        return $this->driver->createRole($name, $password, $privileges);
    }

    public function dropRole(string $name, bool $ifExists = false): bool
    {
        return $this->driver->dropRole($name, $ifExists);
    }

    /**
     * @return array<string>|false
     */
    public function listExtensions(): array|false
    {
        return $this->driver->listExtensions();
    }

    public function createExtension(string $name): bool
    {
        return $this->driver->createExtension($name);
    }

    public function dropExtension(string $name, bool $ifExists = false): bool
    {
        return $this->driver->dropExtension($name, $ifExists);
    }

    public function createDatabase(string $name): bool
    {
        return $this->driver->createDatabase($name);
    }

    /**
     * TRUNCATE empty a table or set of tables.
     *
     * TRUNCATE quickly removes all rows from a set of tables. It has the same effect as an unqualified DELETE on
     * each table, but since it does not actually scan the tables it is faster. Furthermore, it reclaims disk space
     * immediately, rather than requiring a subsequent VACUUM operation. This is most useful on large tables.
     *
     * @param string $tableName       The name of the table(s) to truncate.  Multiple tables are supported.
     * @param bool   $only            Only the named table is truncated. If FALSE, the table and all its descendant tables (if any) are truncated.
     * @param bool   $restartIdentity Automatically restart sequences owned by columns of the truncated table(s).  The default is to no restart.
     * @param bool   $cascade         If TRUE, automatically truncate all tables that have foreign-key references to any of the named tables, or
     *                                to any tables added to the group due to CASCADE.  If FALSE, Refuse to truncate if any of the tables have
     *                                foreign-key references from tables that are not listed in the command. FALSE is the default.
     */
    public function truncate(string $tableName, bool $only = false, bool $restartIdentity = false, bool $cascade = false): bool
    {
        return $this->driver->truncate($tableName, $only, $restartIdentity, $cascade);
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
