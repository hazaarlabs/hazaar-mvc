<?php

/**
 * @file        Hazaar/DBI/DBI.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\DBI;

use Hazaar\Application;
use Hazaar\Application\Config;
use Hazaar\Application\FilePath;
use Hazaar\DBI\Exception\ConnectionFailed;
use Hazaar\DBI\Exception\DriverNotFound;
use Hazaar\DBI\Exception\NotConfigured;
use Hazaar\Loader;

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
class Adapter implements Interface\API\Constraint, Interface\API\Extension, Interface\API\Group, Interface\API\Index, Interface\API\Schema, Interface\API\Sequence, Interface\API\SQL, Interface\API\StoredFunction, Interface\API\Table, Interface\API\Transaction, Interface\API\Trigger, Interface\API\User, Interface\API\View, Interface\API\Statement
{
    /**
     * @var array<string, array{cipher:string,checkstring:string}|int|string>
     */
    public static array $defaultConfig = [
        'encrypt' => [
            'cipher' => 'aes-256-ctr',
            'checkstring' => '!!',
        ],
        'timezone' => 'UTC',
    ];

    /**
     * @var array<mixed>
     */
    public array $config;

    /**
     * @var array<string,self>
     */
    private static array $instances = [];

    /**
     * @var array<string,Config>
     */
    private static array $loadedConfigs = [];

    private DBD\Interface\Driver $driver;

    private Manager $schemaManager;

    /**
     * @var array<Manager>
     */
    private static array $managerInstances = [];

    private string $env = APPLICATION_ENV;

    /**
     * Hazaar DBI Constructor.
     *
     * @param array<mixed>|string $config An array of configuration options to instantiate the DBI Adapter.  This can
     *                                    also be a Hazaar configuration environment if DBI is being used by a Hazaar
     *                                    application.
     */
    public function __construct(null|array|string $config = null)
    {
        if (is_array($config)) {
            $config = array_merge($config, self::$defaultConfig);
        } else {
            if (is_string($config)) {
                $this->env = $config;
            }
            $config = $this->loadConfig($this->env)->toArray();
        }
        if (!isset($config['driver'])) {
            throw new NotConfigured();
        }
        $this->config = $config;
        if ($this->env && !array_key_exists($this->env, self::$instances)) {
            self::$instances[$this->env] = $this;
        }
        $this->reconfigure();
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
    public function getSchemaManager(?\Closure $logCallback = null): Manager
    {
        if (!isset($this->schemaManager)) {
            $this->schemaManager = self::getSchemaManagerInstance($this->env, $logCallback);
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
    public static function getInstance(?string $configEnv = APPLICATION_ENV): self
    {
        if (array_key_exists($configEnv, self::$instances)) {
            return self::$instances[$configEnv];
        }

        return new self($configEnv);
    }

    /**
     * Return an existing or create a new instance of a DBI Schema Manager;.
     */
    public static function getSchemaManagerInstance(?string $configEnv = null, ?callable $logCallback = null): Manager
    {
        if (isset(self::$managerInstances[$configEnv])) {
            return self::$managerInstances[$configEnv];
        }

        try {
            $config = self::loadConfig($configEnv);
            $config['environment'] = $configEnv;
        } catch (\Exception $e) {
            throw new \Exception("DBI is not configured for APPLICATION_ENV '".APPLICATION_ENV."'");
        }

        return new Manager($config->toArray(), $logCallback);
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

    /**
     * Returns a Query object for the specified table name.
     *
     * @param-out string $configName  The name of the configuration that was loaded
     */
    public static function loadConfig(?string &$configName = null): Config
    {
        $configName ??= APPLICATION_ENV;
        if (!array_key_exists($configName, Adapter::$loadedConfigs)) {
            self::$defaultConfig['timezone'] = date_default_timezone_get();
            if (!Config::$overridePaths) {
                Config::$overridePaths = Application::getConfigOverridePaths();
            }
            $config = Config::getInstance('database', $configName, self::$defaultConfig, true);
            self::$loadedConfigs[$configName] = $config;
        }

        return self::$loadedConfigs[$configName];
    }

    public function listConstraints($table = null, $type = null, $invertType = false): array|false
    {
        if (!$this->driver instanceof Interface\API\Constraint) {
            throw new \BadMethodCallException('Driver does not support constraints');
        }

        return $this->driver->listConstraints($table, $type, $invertType);
    }

    public function addConstraint(string $constraintName, array $info): bool
    {
        if (!$this->driver instanceof Interface\API\Constraint) {
            throw new \BadMethodCallException('Driver does not support constraints');
        }

        return $this->driver->addConstraint($constraintName, $info);
    }

    public function dropConstraint(string $constraintName, string $tableName, bool $ifExists = false, bool $cascade = false): bool
    {
        if (!$this->driver instanceof Interface\API\Constraint) {
            throw new \BadMethodCallException('Driver does not support constraints');
        }

        return $this->driver->dropConstraint($constraintName, $tableName, $ifExists, $cascade);
    }

    public function listExtensions(): array
    {
        if (!$this->driver instanceof Interface\API\Extension) {
            throw new \BadMethodCallException('Driver does not support extensions');
        }

        return $this->driver->listExtensions();
    }

    public function extensionExists(string $name): bool
    {
        if (!$this->driver instanceof Interface\API\Extension) {
            throw new \BadMethodCallException('Driver does not support extensions');
        }

        return $this->driver->extensionExists($name);
    }

    public function createExtension(string $name): bool
    {
        if (!$this->driver instanceof Interface\API\Extension) {
            throw new \BadMethodCallException('Driver does not support extensions');
        }

        return $this->driver->createExtension($name);
    }

    public function dropExtension(string $name, bool $ifExists = false): bool
    {
        if (!$this->driver instanceof Interface\API\Extension) {
            throw new \BadMethodCallException('Driver does not support extensions');
        }

        return $this->driver->dropExtension($name, $ifExists);
    }

    public function listGroups(): array
    {
        if (!$this->driver instanceof Interface\API\Group) {
            throw new \BadMethodCallException('Driver does not support groups');
        }

        return $this->driver->listGroups();
    }

    public function createGroup(string $groupName): bool
    {
        if (!$this->driver instanceof Interface\API\Group) {
            throw new \BadMethodCallException('Driver does not support groups');
        }

        return $this->driver->createGroup($groupName);
    }

    public function dropGroup(string $groupName): bool
    {
        if (!$this->driver instanceof Interface\API\Group) {
            throw new \BadMethodCallException('Driver does not support groups');
        }

        return $this->driver->dropGroup($groupName);
    }

    public function addToGroup(string $roleName, string $parentRoleName): bool
    {
        if (!$this->driver instanceof Interface\API\Group) {
            throw new \BadMethodCallException('Driver does not support groups');
        }

        return $this->driver->addToGroup($roleName, $parentRoleName);
    }

    public function removeFromGroup(string $roleName, string $parentRoleName): bool
    {
        if (!$this->driver instanceof Interface\API\Group) {
            throw new \BadMethodCallException('Driver does not support groups');
        }

        return $this->driver->removeFromGroup($roleName, $parentRoleName);
    }

    public function listIndexes(?string $tableName = null): array
    {
        if (!$this->driver instanceof Interface\API\Index) {
            throw new \BadMethodCallException('Driver does not support indexes');
        }

        return $this->driver->listIndexes($tableName);
    }

    public function indexExists(string $indexName, ?string $tableName = null): bool
    {
        if (!$this->driver instanceof Interface\API\Index) {
            throw new \BadMethodCallException('Driver does not support indexes');
        }

        return $this->driver->indexExists($indexName, $tableName);
    }

    public function createIndex(string $indexName, string $tableName, mixed $idxInfo): bool
    {
        if (!$this->driver instanceof Interface\API\Index) {
            throw new \BadMethodCallException('Driver does not support indexes');
        }

        return $this->driver->createIndex($indexName, $tableName, $idxInfo);
    }

    public function dropIndex(string $indexName, bool $ifExists = false): bool
    {
        if (!$this->driver instanceof Interface\API\Index) {
            throw new \BadMethodCallException('Driver does not support indexes');
        }

        return $this->driver->dropIndex($indexName, $ifExists);
    }

    public function getSchemaName(): string
    {
        if (!$this->driver instanceof Interface\API\Schema) {
            throw new \BadMethodCallException('Driver does not support schemas');
        }

        return $this->driver->getSchemaName();
    }

    public function schemaExists(?string $schemaName = null): bool
    {
        if (!$this->driver instanceof Interface\API\Schema) {
            throw new \BadMethodCallException('Driver does not support schemas');
        }

        return $this->driver->schemaExists($schemaName);
    }

    public function createSchema(?string $schemaName = null): bool
    {
        if (!$this->driver instanceof Interface\API\Schema) {
            throw new \BadMethodCallException('Driver does not support schemas');
        }

        return $this->driver->createSchema($schemaName);
    }

    public function listSequences(): array
    {
        if (!$this->driver instanceof Interface\API\Sequence) {
            throw new \BadMethodCallException('Driver does not support sequences');
        }

        return $this->driver->listSequences();
    }

    public function sequenceExists(string $sequenceName): bool
    {
        if (!$this->driver instanceof Interface\API\Sequence) {
            throw new \BadMethodCallException('Driver does not support sequences');
        }

        return $this->driver->sequenceExists($sequenceName);
    }

    public function describeSequence(string $name): array|false
    {
        if (!$this->driver instanceof Interface\API\Sequence) {
            throw new \BadMethodCallException('Driver does not support sequences');
        }

        return $this->driver->describeSequence($name);
    }

    public function createSequence(string $name, array $sequenceInfo, bool $ifNotExists = false): bool
    {
        if (!$this->driver instanceof Interface\API\Sequence) {
            throw new \BadMethodCallException('Driver does not support sequences');
        }

        return $this->driver->createSequence($name, $sequenceInfo, $ifNotExists);
    }

    public function dropSequence(string $name, bool $ifExists = false): bool
    {
        if (!$this->driver instanceof Interface\API\Sequence) {
            throw new \BadMethodCallException('Driver does not support sequences');
        }

        return $this->driver->dropSequence($name, $ifExists);
    }

    public function nextSequenceValue(string $name): false|int
    {
        if (!$this->driver instanceof Interface\API\Sequence) {
            throw new \BadMethodCallException('Driver does not support sequences');
        }

        return $this->driver->nextSequenceValue($name);
    }

    public function setSequenceValue(string $name, int $value): bool
    {
        if (!$this->driver instanceof Interface\API\Sequence) {
            throw new \BadMethodCallException('Driver does not support sequences');
        }

        return $this->driver->setSequenceValue($name, $value);
    }

    public function createDatabase(string $name): bool
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->createDatabase($name);
    }

    public function exec(string $sql): false|int
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->exec($sql);
    }

    public function query(string $sql): false|Result
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->query($sql);
    }

    public function quote(mixed $string, int $type = \PDO::PARAM_STR): false|string
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->quote($string, $type);
    }

    public function setTimezone(string $tz): bool
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->setTimezone($tz);
    }

    public function errorInfo(): array|false
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->errorInfo();
    }

    public function errorCode(): string
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->errorCode();
    }

    public function getQueryBuilder(): Interface\QueryBuilder
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->getQueryBuilder();
    }

    public function repair(): bool
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->repair();
    }

    public function lastQueryString(): string
    {
        if (!$this->driver instanceof Interface\API\SQL) {
            throw new \BadMethodCallException('Driver does not support SQL');
        }

        return $this->driver->lastQueryString();
    }

    public function listFunctions(bool $includeParameters = false): array
    {
        if (!$this->driver instanceof Interface\API\StoredFunction) {
            throw new \BadMethodCallException('Driver does not support stored functions');
        }

        return $this->driver->listFunctions($includeParameters);
    }

    public function functionExists(string $functionName, ?string $argTypes = null): bool
    {
        if (!$this->driver instanceof Interface\API\StoredFunction) {
            throw new \BadMethodCallException('Driver does not support stored functions');
        }

        return $this->driver->functionExists($functionName, $argTypes);
    }

    public function describeFunction(string $name): array|false
    {
        if (!$this->driver instanceof Interface\API\StoredFunction) {
            throw new \BadMethodCallException('Driver does not support stored functions');
        }

        return $this->driver->describeFunction($name);
    }

    public function createFunction(string $name, mixed $spec, bool $replace = false): bool
    {
        if (!$this->driver instanceof Interface\API\StoredFunction) {
            throw new \BadMethodCallException('Driver does not support stored functions');
        }

        return $this->driver->createFunction($name, $spec, $replace);
    }

    public function dropFunction(string $name, null|array|string $argTypes = null, bool $cascade = false, bool $ifExists = false): bool
    {
        if (!$this->driver instanceof Interface\API\StoredFunction) {
            throw new \BadMethodCallException('Driver does not support stored functions');
        }

        return $this->driver->dropFunction($name, $argTypes, $cascade, $ifExists);
    }

    public function listTables(): array
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->listTables();
    }

    public function tableExists(string $tableName): bool
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->tableExists($tableName);
    }

    public function createTable(string $tableName, mixed $columns): bool
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->createTable($tableName, $columns);
    }

    public function describeTable(string $tableName, ?string $sort = null): array|false
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->describeTable($tableName, $sort);
    }

    public function renameTable(string $fromName, string $toName): bool
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->renameTable($fromName, $toName);
    }

    public function dropTable(string $name, bool $ifExists = false, bool $cascade = false): bool
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->dropTable($name, $ifExists, $cascade);
    }

    public function addColumn(string $tableName, mixed $columnSpec): bool
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->addColumn($tableName, $columnSpec);
    }

    public function alterColumn(string $tableName, string $column, mixed $columnSpec): bool
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->alterColumn($tableName, $column, $columnSpec);
    }

    public function dropColumn(string $tableName, string $column, bool $ifExists = false): bool
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->dropColumn($tableName, $column, $ifExists);
    }

    public function truncate(string $tableName, bool $only = false, bool $restartIdentity = false, bool $cascade = false): bool
    {
        if (!$this->driver instanceof Interface\API\Table) {
            throw new \BadMethodCallException('Driver does not support tables');
        }

        return $this->driver->truncate($tableName, $only, $restartIdentity, $cascade);
    }

    public function begin(): bool
    {
        if (!$this->driver instanceof Interface\API\Transaction) {
            throw new \BadMethodCallException('Driver does not support transactions');
        }

        return $this->driver->begin();
    }

    public function commit(): bool
    {
        if (!$this->driver instanceof Interface\API\Transaction) {
            throw new \BadMethodCallException('Driver does not support transactions');
        }

        return $this->driver->commit();
    }

    public function cancel(): bool
    {
        if (!$this->driver instanceof Interface\API\Transaction) {
            throw new \BadMethodCallException('Driver does not support transactions');
        }

        return $this->driver->cancel();
    }

    public function listTriggers(?string $tableName = null): array
    {
        if (!$this->driver instanceof Interface\API\Trigger) {
            throw new \BadMethodCallException('Driver does not support triggers');
        }

        return $this->driver->listTriggers($tableName);
    }

    public function triggerExists(string $triggerName, string $tableName): bool
    {
        if (!$this->driver instanceof Interface\API\Trigger) {
            throw new \BadMethodCallException('Driver does not support triggers');
        }

        return $this->driver->triggerExists($triggerName, $tableName);
    }

    public function describeTrigger(string $triggerName): array|false
    {
        if (!$this->driver instanceof Interface\API\Trigger) {
            throw new \BadMethodCallException('Driver does not support triggers');
        }

        return $this->driver->describeTrigger($triggerName);
    }

    public function createTrigger(string $triggerName, string $tableName, mixed $spec = []): bool
    {
        if (!$this->driver instanceof Interface\API\Trigger) {
            throw new \BadMethodCallException('Driver does not support triggers');
        }

        return $this->driver->createTrigger($triggerName, $tableName, $spec);
    }

    public function dropTrigger(string $triggerName, string $tableName, bool $ifExists = false, bool $cascade = false): bool
    {
        if (!$this->driver instanceof Interface\API\Trigger) {
            throw new \BadMethodCallException('Driver does not support triggers');
        }

        return $this->driver->dropTrigger($triggerName, $tableName, $ifExists, $cascade);
    }

    public function listUsers(): array
    {
        if (!$this->driver instanceof Interface\API\User) {
            throw new \BadMethodCallException('Driver does not support users');
        }

        return $this->driver->listUsers();
    }

    public function createUser(string $name, ?string $password = null, array $privileges = []): bool
    {
        if (!$this->driver instanceof Interface\API\User) {
            throw new \BadMethodCallException('Driver does not support users');
        }

        return $this->driver->createUser($name, $password, $privileges);
    }

    public function dropUser(string $name, bool $ifExists = false): bool
    {
        if (!$this->driver instanceof Interface\API\User) {
            throw new \BadMethodCallException('Driver does not support users');
        }

        return $this->driver->dropUser($name, $ifExists);
    }

    public function grant(array|string $role, string $to, string $on): bool
    {
        if (!$this->driver instanceof Interface\API\User) {
            throw new \BadMethodCallException('Driver does not support users');
        }

        return $this->driver->grant($role, $to, $on);
    }

    public function revoke(array|string $role, string $from, string $on): bool
    {
        if (!$this->driver instanceof Interface\API\User) {
            throw new \BadMethodCallException('Driver does not support users');
        }

        return $this->driver->revoke($role, $from, $on);
    }

    public function listViews(): array
    {
        if (!$this->driver instanceof Interface\API\View) {
            throw new \BadMethodCallException('Driver does not support views');
        }

        return $this->driver->listViews();
    }

    public function viewExists(string $viewName): bool
    {
        if (!$this->driver instanceof Interface\API\View) {
            throw new \BadMethodCallException('Driver does not support views');
        }

        return $this->driver->viewExists($viewName);
    }

    public function describeView(string $name): array|false
    {
        if (!$this->driver instanceof Interface\API\View) {
            throw new \BadMethodCallException('Driver does not support views');
        }

        return $this->driver->describeView($name);
    }

    public function createView(string $name, mixed $content, bool $replace = false): bool
    {
        if (!$this->driver instanceof Interface\API\View) {
            throw new \BadMethodCallException('Driver does not support views');
        }

        return $this->driver->createView($name, $content, $replace);
    }

    public function dropView(string $name, bool $ifExists = false, bool $cascade = false): bool
    {
        if (!$this->driver instanceof Interface\API\View) {
            throw new \BadMethodCallException('Driver does not support views');
        }

        return $this->driver->dropView($name, $ifExists, $cascade);
    }

    public function prepare(string $sql): \PDOStatement
    {
        if (!$this->driver instanceof Interface\API\Statement) {
            throw new \BadMethodCallException('Driver does not support prepared statements');
        }

        return $this->driver->prepare($sql);
    }

    private function getDriverClass(string $driver): string
    {
        return 'Hazaar\DBI\DBD\\'.ucfirst($driver);
    }

    private function reconfigure(bool $reconnect = false): bool
    {
        if (!$this->config['driver']) {
            throw new \Exception('No DBI driver specified!');
        }
        $driverClass = $this->getDriverClass($this->config['driver']);
        if (!class_exists($driverClass)) {
            throw new DriverNotFound($this->config['driver']);
        }

        try {
            $this->driver = new $driverClass($this->config);
            if (isset($this->config['timezone'])) {
                $this->setTimezone($this->config['timezone']);
            }
        } catch (\PDOException $e) {
            if (7 === $e->getCode()) {
                throw new ConnectionFailed($this->config['host']);
            }

            throw $e;
        }
        // if (defined('HAZAAR_VERSION') && (isset($this->config['encrypt']['table']) && !isset($this->config['encrypt']['key']))) {
        //     $keyfile = Loader::getFilePath(FilePath::CONFIG, $this->config['encrypt']['keyfile'] ?? '.db_key');
        //     if (null === $keyfile) {
        //         throw new \Exception('DBI keyfile is missing.  Database encryption will not work!');
        //     }
        //     $this->config['encrypt']['key'] = trim(file_get_contents($keyfile));
        // }

        return true;
    }
}
