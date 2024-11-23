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
use Hazaar\DBI\DBD\BaseDriver;
use Hazaar\DBI\Exception\ConnectionFailed;
use Hazaar\DBI\Exception\DriverNotFound;
use Hazaar\DBI\Exception\DriverNotSpecified;
use Hazaar\DBI\Exception\NotConfigured;
use Hazaar\DBI\Schema\Manager;
use Hazaar\File\Dir;
use Hazaar\Loader;
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
 * @method false|string lastInsertId()
 * @method false|int    exec(string $sql)
 * @method false|int    delete(string $tableName, mixed $criteria = [], array $from = [])
 * @method string       getSchemaName()
 * @method false|string quote(string $string, int $parameterType = \PDO::PARAM_STR)
 * @method bool         setTimezone(string $timezone)
 * @method bool         repair(?string $table = null)
 * @method array        errorInfo()
 * @method mixed        errorCode()
 * @method bool         truncate(string $tableName,bool $only = false,bool $restartIdentity = false,bool $cascade = false)
 * @method bool         createDatabase(string $name)
 * @method string       schemaName(string $table)
 * @method bool         schemaExists(string $schemaName)
 * @method bool         createSchema(string $schemaName)
 * @method bool         beginTransaction()
 * @method bool         commit()
 * @method bool         rollback()
 * @method bool         inTransaction()
 * @method mixed        getAttribute(int $attribute)
 * @method bool         setAttribute(int $attribute, mixed $value)
 * @method bool         createRole(string $roleName, ?string $password = null, array $privileges = [])
 * @method bool         dropRole(string $name, bool $ifExists = false)
 * @method array|false  listUsers()
 * @method array|false  listGroups()
 * @method array        listTables()
 * @method bool         tableExists(string $tableName)
 * @method bool         createTable(string $tableName, mixed $columns)
 * @method bool         renameTable(string $fromName, string $toName)
 * @method array|false  describeTable(string $tableName, ?string $sort = null)
 * @method bool         dropTable(string $table, bool $cascade = false, bool $ifExists = false)
 * @method bool         addColumn(string $tableName, mixed $columnSpec)
 * @method bool         alterColumn(string $tableName, string $column, mixed $columnSpec)
 * @method bool         dropColumn(string $tableName, string $column, bool $ifExists = false)
 * @method array|false  listPrimaryKeys(?string $table = null)
 * @method array|false  listForeignKeys(?string $table = null)
 * @method array|false  listConstraints(?string $table = null, ?string $type = null, bool $invertType = false)
 * @method bool         addConstraint(string $constraintName, mixed $info)
 * @method bool         dropConstraint(string $constraintName, string $tableName, bool $cascade = false, bool $ifExists = false)
 * @method array|false  listIndexes(?string $tableName = null)
 * @method bool         createIndex(string $indexName, string $tableName, array $idxInfo = [])
 * @method bool         dropIndex(string $indexName, bool $ifExists = false)
 * @method array|false  listViews()
 * @method bool         createView(string $viewName, mixed $content)
 * @method bool         viewExists(string $viewName)
 * @method array|false  describeView(string $viewName)
 * @method bool         dropView(string $viewName, bool $cascade = false, bool $ifExists = false)
 * @method array|false  listFunctions(?string $schema = null, bool $includeParameters = false)
 * @method bool         createFunction(string $functionName, mixed $content)
 * @method array|false  describeFunction(string $functionName)
 * @method bool         dropFunction(string $functionName, null|array|string $argTypes = null, bool $cascade = false, bool $ifExists = false)
 * @method array|false  listTriggers(?string $tableName = null, ?string $schema = null)
 * @method bool         createTrigger(string $functionName, string $tableName, mixed $content)
 * @method array|false  describeTrigger(string $functionName, ?string $schemaName = null)
 * @method bool         dropTrigger(string $functionName, null|array|string $argTypes = null, bool $cascade = false, bool $ifExists = false)
 * @method array|false  listExtensions()
 * @method bool         createExtension(string $name)
 * @method bool         dropExtension(string $name, bool $ifExists = false)
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
    public BaseDriver $driver;

    /**
     * @var array<Config>
     */
    private static array $loadedConfigs = [];
    private ?Manager $schemaManager = null;

    /**
     * @var array<BaseDriver>
     */
    private static array $connections = [];

    /**
     * @var array<Adapter>
     */
    private static array $instances = [];

    /**
     * @var array<Manager>
     */
    private static array $managerInstances = [];

    // Prepared statements
    /**
     * @var array<\PDOStatement>
     */
    private array $statements = [];
    private ?string $dsn = null;

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
        $this->reconfigure();
    }

    /**
     * @param array<mixed> $args
     */
    public function __call(string $method, array $args): mixed
    {
        if (!method_exists($this->driver, $method)) {
            throw new \Exception("Call to unknown method: '{$method}'");
        }

        return call_user_func_array([$this->driver, $method], $args);
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
    public static function getInstance(?string $configEnv = null): Adapter
    {
        if (array_key_exists($configEnv, self::$instances)) {
            return self::$instances[$configEnv];
        }

        return new Adapter($configEnv);
    }

    /**
     * Return an existing or create a new instance of a DBI Schema Manager;.
     */
    public static function getSchemaManagerInstance(?string $configEnv = null, ?callable $logCallback = null): Manager
    {
        if (array_key_exists($configEnv, self::$managerInstances)) {
            return self::$managerInstances[$configEnv];
        }
        if (!($config = self::getDefaultConfig($configEnv))) {
            throw new \Exception("DBI is not configured for APPLICATION_ENV '".APPLICATION_ENV."'");
        }

        return new Manager($config, $logCallback);
    }

    public static function getDriverClass(string $driver): string
    {
        return 'Hazaar\DBI\DBD\\'.ucfirst($driver);
    }

    public static function setDefaultConfig(string $config, ?string $env = null): bool
    {
        if (!defined('HAZAAR_VERSION')) {
            return false;
        }
        if (!$env) {
            $env = APPLICATION_ENV;
        }
        Adapter::$defaultConfig[$env] = $config;

        return true;
    }

    public static function getDefaultConfig(?string &$configName = null): false|Map
    {
        if (!defined('HAZAAR_VERSION')) {
            return false;
        }
        if (!$configName) {
            $configName = APPLICATION_ENV;
        }
        if (!array_key_exists($configName, Adapter::$loadedConfigs)) {
            self::$defaultConfig['timezone'] = date_default_timezone_get();
            if (!Config::$overridePaths) {
                Config::$overridePaths = Application::getConfigOverridePaths();
            }
            $config = Config::getInstance('database', $configName, self::$defaultConfig, FILE_PATH_CONFIG, true);
            if (!$config->loaded()) {
                return false;
            }
            self::$loadedConfigs[$configName] = $config;
        }

        return self::$loadedConfigs[$configName];
    }

    /**
     * @param array<int, bool> $driverOptions
     */
    public function connect(
        ?string $dsn = null,
        ?string $username = null,
        ?string $password = null,
        ?array $driverOptions = null,
        bool $reconnect = false
    ): bool {
        if (null === $dsn) {
            $dsn = $this->dsn;
        }
        $driver = ucfirst(substr($dsn, 0, strpos($dsn, ':')));
        if (!$driver) {
            throw new DriverNotSpecified();
        }
        $DBD = Adapter::getDriverClass($driver);
        if (!class_exists($DBD)) {
            throw new DriverNotFound($driver);
        }
        $this->driver = new $DBD($this, Map::_(array_unflatten(substr($dsn, strpos($dsn, ':') + 1))));
        if (!$driverOptions) {
            $driverOptions = [];
        }
        $driverOptions = array_replace([
            \PDO::ATTR_STRINGIFY_FETCHES => false,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ], $driverOptions);
        if (!$this->driver->connect($dsn, $username, $password, $driverOptions)) {
            return false;
        }
        if ($this->config->has('timezone')) {
            $this->setTimezone($this->config['timezone']);
        }

        return true;
    }

    public function getDriver(): string
    {
        $class = get_class($this->driver);

        return substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * @return array<string>
     */
    public function getAvailableDrivers(): array
    {
        $drivers = [];
        $dir = new Dir(dirname(__FILE__).'/DBD');
        while ($file = $dir->read()) {
            if (preg_match('/class (\w*) extends BaseDriver\W/m', $file->getContents(), $matches)) {
                $drivers[] = $matches[1];
            }
        }

        return $drivers;
    }

    /**
     * Build a sub-query using an existing query.
     *
     * @param string|Table $subquery Table The existing query to use as the sub-query
     * @param string       $name     The named alias of the sub-query
     */
    public function subquery(string|Table $subquery, string $name): Table
    {
        return new Table($this, $subquery, $name);
    }

    public function query(string $sql): false|Result
    {
        $result = false;
        $retries = 0;
        while ($retries++ < 3) {
            $result = $this->driver->query($sql);
            if ($result instanceof \PDOStatement) {
                return new Result($this, $result);
            }
            $error = $this->errorCode();
            if (!('57P01' === $error || 'HY000' === $error)) {
                break;
            }
            $this->reconfigure(true);
        }

        return $result;
    }

    public function exists(string $table, mixed $criteria = []): bool
    {
        return $this->table($table)->exists($criteria);
    }

    public function table(string $name, ?string $alias = null): Table
    {
        return new Table($this, $name, $alias);
    }

    public function from(string $name, ?string $alias = null): Table
    {
        return $this->table($name, $alias);
    }

    /**
     * @param array<string> $args
     */
    public function call(string $method, array $args = [], mixed $criteria = null): Result
    {
        $sql = 'SELECT * FROM '.$method.'('.$this->driver->prepareValues($args).')';
        if (null !== $criteria) {
            $sql .= ' WHERE '.$this->driver->prepareCriteria($criteria);
        }
        $sql .= ';';

        return $this->query($sql);
    }

    /**
     * Prepared statements.
     */
    public function prepare(string $sql, ?string $name = null): Result
    {
        $statement = $this->driver->prepare($sql);
        if (false === $statement) {
            throw new \Exception('DBI failed to prepare the SQL statement.');
        }
        if ($name) {
            $this->statements[$name] = $statement;
        } else {
            $this->statements[] = $statement;
        }

        return new Result($this, $statement);
    }

    /**
     * @param array<mixed> $inputParameters
     */
    public function execute(string $name, array $inputParameters): bool
    {
        if (!($statement = ake($this->statements, $name)) instanceof \PDOStatement) {
            return false;
        }
        if (!is_array($inputParameters)) {
            $inputParameters = [$inputParameters];
        }

        return $statement->execute($inputParameters);
    }

    /**
     * @return array<\PDOStatement>
     */
    public function getPreparedStatements(): array
    {
        return $this->statements;
    }

    /**
     * @return array<string>
     */
    public function listPreparedStatements(): array
    {
        return array_keys($this->statements);
    }

    /**
     * Returns an instance of the Hazaar\DBI\Schema\Manager for managing database schema versions.
     *
     * @return Manager
     */
    public function getSchemaManager()
    {
        if (!$this->schemaManager instanceof Manager) {
            $this->schemaManager = new Manager($this->config);
        }

        return $this->schemaManager;
    }

    /**
     * Perform and "upsert".
     *
     * An upsert is an INSERT, that when it fails, columns can be updated in the existing row.
     *
     * @param string                   $tableName      the table to insert a record into
     * @param mixed                    $fields         the fields to be inserted
     * @param mixed                    $returning      a column to return when the row is inserted (usually the primary key)
     * @param null|array<mixed>|string $conflictTarget the column(s) to check for a conflict.  If the conflict is found,
     *                                                 the row will be updated.
     * @param array<mixed>             $conflictUpdate
     *
     * @return array<mixed>|false|int
     */
    public function insert(
        string $tableName,
        mixed $fields,
        mixed $returning = null,
        null|array|string $conflictTarget = null,
        ?array $conflictUpdate = null,
        ?Table $table = null
    ): array|false|int {
        $result = $this->driver->insert(
            $tableName,
            $this->encrypt($tableName, $fields),
            $returning,
            $conflictTarget,
            $conflictUpdate,
            $table
        );
        if ($result instanceof \PDOStatement) {
            $result = new Result($this, $result);

            return $result->fetch();
        }

        return $result;
    }

    /**
     * @param array<string> $from
     * @param array<string> $tables
     *
     * @return array<mixed>|false|int
     */
    public function update(
        string $tableName,
        mixed $fields,
        mixed $criteria = [],
        array $from = [],
        mixed $returning = [],
        array $tables = []
    ): array|false|int {
        $result = $this->driver->update($tableName, $this->encrypt($tableName, $fields), $criteria, $from, $returning, $tables);
        if ($result instanceof \PDOStatement) {
            $result = new Result($this, $result);
            if (is_array(BaseDriver::$selectGroups) && count(BaseDriver::$selectGroups) > 0) {
                $result->setSelectGroups(BaseDriver::$selectGroups);
            }
            $fetchArg = $result->hasSelectGroups() ? \PDO::FETCH_NAMED : \PDO::FETCH_ASSOC;

            return ((is_string($returning) && $returning) || (is_array($returning) && count($returning) > 0)) ? $result->fetchAll($fetchArg) : $result->fetch($fetchArg);
        }

        return $result;
    }

    public function encrypt(string $table, mixed &$data): mixed
    {
        if (null === $data
            || !(is_array($data) && count($data) > 0)
            || ($encryptedFields = ake(ake($this->config['encrypt'], 'table'), $table)) === null) {
            return $data;
        }
        $cipher = $this->config->get('encrypt.cipher');
        $key = $this->config->get('encrypt.key', '0000');
        $checkstring = $this->config->get('encrypt.checkstring');
        foreach ($data as $column => &$value) {
            if (!($encryptedFields instanceof Map && $encryptedFields->contains($column))
                && true !== $encryptedFields) {
                continue;
            }
            if (!is_string($value)) {
                throw new \Exception('Trying to encrypt non-string field: '.$column);
            }
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
            $value = base64_encode($iv.openssl_encrypt($checkstring.$value, $cipher, $key, OPENSSL_RAW_DATA, $iv));
        }

        return $data;
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
     * Parse an SQL query into a DBI Table query so that it can be manipulated.
     *
     * @param string $sql The SQL query to parse
     */
    public function parseSQL(string $sql): Table\SQL
    {
        return new Table\SQL($this, $sql);
    }

    /**
     * Grant privileges on a table to a user.
     *
     * @param string               $table      the name of the table to grant privileges on
     * @param string               $user       The name of the user who is being given the privileges
     * @param array<string>|string $privileges One or more privileges being applied.  Example: (string)'ALL' and (array)['INSERT', 'UPDATE', 'DELETE'] are both valid.
     */
    public function grant(string $table, string $user, array|string $privileges = 'ALL'): bool
    {
        $table = $this->driver->schemaName($table);
        if (!is_array($privileges)) {
            $privileges = [$privileges];
        }
        $privilegeString = implode(', ', $privileges);

        return $this->driver->exec("GRANT {$privilegeString} ON {$table} TO {$user};");
    }

    private function reconfigure(bool $reconnect = false): bool
    {
        $user = ($this->config->has('user') ? $this->config['user'] : null);
        $password = ($this->config->has('password') ? $this->config['password'] : null);
        if ($this->config->has('dsn')) {
            $this->dsn = $this->config['dsn'];
        } else {
            $DBD = Adapter::getDriverClass($this->config['driver']);
            if (!class_exists($DBD)) {
                return false;
            }
            $this->dsn = $DBD::mkdsn($this->config);
        }
        $driverOptions = [];
        if ($this->config->has('options')) {
            $driverOptions = $this->config['options']->toArray();
            foreach ($driverOptions as $key => $value) {
                if (($constKey = constant('\PDO::'.$key)) === null) {
                    continue;
                }
                $driverOptions[$constKey] = $value;
                unset($driverOptions[$key]);
            }
        }
        $driver = ucfirst(substr($this->dsn, 0, strpos($this->dsn, ':')));
        if (!array_key_exists($driver, Adapter::$connections)) {
            Adapter::$connections[$driver] = [];
        }
        $hash = md5(serialize($this->config->toArray()));
        if (true !== $reconnect && array_key_exists($hash, Adapter::$connections)) {
            $this->driver = Adapter::$connections[$hash];
        } else {
            if (!$this->connect($this->dsn, $user, $password, $driverOptions, $reconnect)) {
                throw new ConnectionFailed(ake($this->config, 'host', 'none'), $this->errorInfo());
            }
            Adapter::$connections[$hash] = $this->driver;
            if ($this->config->has('schema')) {
                $this->driver->setSchemaName($this->config->get('schema'));
            }
        }
        if (defined('HAZAAR_VERSION') && ($this->config->has('encrypt.table') && !$this->config->has('encrypt.key'))) {
            $keyfile = Loader::getFilePath(FILE_PATH_CONFIG, $this->config->get('encrypt.keyfile', '.db_key'));
            if (null === $keyfile) {
                throw new \Exception('DBI keyfile is missing.  Database encryption will not work!');
            }
            $this->config['encrypt']['key'] = trim(file_get_contents($keyfile));
        }

        return true;
    }
}
