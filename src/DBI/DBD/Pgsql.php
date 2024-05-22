<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD;

use Hazaar\DBI\Adapter;
use Hazaar\Map;
use PgSql\Connection;

class Pgsql extends BaseDriver
{
    /**
     * @var array<string> The elements that make up the DSN for the PostgreSQL database connection
     */
    public static array $dsnElements = [
        'host',
        'port',
        'dbname',
        'user',
        'password',
    ];

    private ?Connection $conn = null; // The connection resource for notifications

    /**
     * Constructor for the Pgsql class.
     *
     * @param Map $config the configuration settings for the Pgsql class
     */
    public function __construct(Adapter $adapter, ?Map $config = null)
    {
        parent::__construct($adapter, $config);
        $this->schemaName = 'public';
    }

    /**
     * Checks if a schema exists in the database.
     *
     * @param string $schemaName the name of the schema to check
     *
     * @return bool returns true if the schema exists, false otherwise
     */
    public function schemaExists($schemaName): bool
    {
        $sql = "SELECT schema_name FROM information_schema.schemata WHERE schema_name = '{$schemaName}';";
        if ($result = $this->query($sql)) {
            return $result->fetch(\PDO::FETCH_ASSOC) ? true : false;
        }

        return false;
    }

    /**
     * Creates a new database schema if it does not already exist.
     *
     * @param string $schemaName the name of the schema to create
     *
     * @return bool returns true if the schema was created successfully, false otherwise
     */
    public function createSchema(string $schemaName): bool
    {
        $sql = "CREATE SCHEMA IF NOT EXISTS {$schemaName};";

        return false !== $this->exec($sql);
    }

    /**
     * Sets the timezone for the database connection.
     *
     * @param string $tz the timezone to set
     *
     * @return bool returns true if the timezone was set successfully, false otherwise
     */
    public function setTimezone(string $tz): bool
    {
        return false != $this->exec("SET TIME ZONE '{$tz}';");
    }

    /**
     * Run a DBD repair process for this database type.
     *
     * @param $table string (optional) Run the repair on a single table
     */
    public function repair(?string $table = null): bool
    {
        /*
         * Fix sequence current values to max value of column
         *
         * See: https://wiki.postgresql.org/wiki/Fixing_Sequences
         */
        $sql = "SELECT quote_ident(PGT.schemaname) || '.' || quote_ident(T.relname) as t, 'SELECT SETVAL(' ||
               quote_literal(quote_ident(PGT.schemaname) || '.' || quote_ident(S.relname)) ||
               ', GREATEST(MAX(' ||quote_ident(C.attname)|| '), 1) ) FROM ' ||
               quote_ident(PGT.schemaname) || '.' || quote_ident(T.relname) || ';' as sql
               FROM pg_class AS S,
                    pg_depend AS D,
                    pg_class AS T,
                    pg_attribute AS C,
                    pg_tables AS PGT
               WHERE S.relkind = 'S'
                   AND S.oid = D.objid
                   AND D.refobjid = T.oid
                   AND D.refobjid = C.attrelid
                   AND D.refobjsubid = C.attnum
                   AND T.relname = PGT.tablename
               ORDER BY S.relname";
        if ($table) {
            $sql = "SELECT t, sql FROM ({$sql}) s WHERE t = '{$this->schemaName}.{$table}';";
        } else {
            $sql .= ';';
        }
        if (($result = $this->query($sql)) === false) {
            throw new \Exception(ake($this->errorInfo(), 2));
        }
        $tables = [];
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $tables[] = $row['t'];
            $this->query($row['sql']);
        }
        // Do a quick vacuum as well.
        if (null === $table) {
            $this->query('VACUUM');
        }

        return true;
    }

    /**
     * Fixes the value before it is used in a database query.
     *
     * @param mixed $value the value to be fixed
     *
     * @return mixed the fixed value
     */
    public function fixValue($value): mixed
    {
        if (!$value) {
            return $value;
        }
        // Convert the 'now()' function call to the standard CURRENT_TIMESTAMP
        if ('now()' == strtolower($value)) {
            return 'CURRENT_TIMESTAMP';
        }
        // Strip any type casts
        if ($pos = strpos($value, '::')) {
            return substr($value, 0, $pos);
        }

        return $value;
    }

    /**
     * Converts the given value to a string representation suitable for use in a database query.
     *
     * @param mixed $string the value to be converted
     *
     * @return string the converted string representation of the value
     */
    public function field($string): string
    {
        if (!is_string($string)) {
            if (is_bool($string)) {
                return strbool($string);
            }
            if (is_array($string) && array_key_exists('schema', $string) && array_key_exists('name', $string)) {
                $string = $string['schema'].'.'.$string['name'];
            } elseif (null === $string) {
                return 'NULL';
            } else {
                return (string) $string;
            }
        } else {
            $string = trim($string);
        }
        // This matches an string that contain a non-word character, which means it is either a function call, concat or
        // at least definitely not a reserved word as all reserved words have only word characters
        if (preg_match('/\W/', $string)) {
            return $string;
        }

        return $this->quoteSpecial($string);
    }

    /**
     * Retrieves a list of tables in the database.
     *
     * @param string $schema the name of the schema to retrieve the tables from. default is current schema
     *
     * @return array<int, array<string>>|false an array of tables, each represented as an associative array with keys 'schema' and 'name'
     */
    public function listTables(?string $schema = null): array|false
    {
        if (!$schema) {
            $schema = $this->schemaName;
        }
        $sql = 'SELECT table_schema as "schema", table_name as name '
            ."FROM information_schema.tables t WHERE table_type = 'BASE TABLE'"
            ." AND table_schema = '{$schema}'"
            .' ORDER BY table_name DESC;';
        if ($result = $this->query($sql)) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Retrieves a list of constraints for a given table or all tables in the schema.
     *
     * @param null|string $table      The name of the table. If null, constraints for all tables in the schema will be retrieved.
     * @param null|string $type       The type of constraints to retrieve. If null, all types of constraints will be retrieved.
     * @param bool        $invertType Whether to invert the constraint type filter. If true, constraints of types other than the specified type will be retrieved.
     *
     * @return array<int, array<string>>|false an array of constraints or false if constraints are not allowed or an error occurred
     */
    public function listConstraints($table = null, $type = null, $invertType = false): array|false
    {
        if (!$this->allowConstraints) {
            return false;
        }
        if ($table) {
            list($schema, $table) = $this->parseSchemaName($table);
        } else {
            $schema = $this->schemaName;
        }
        $constraints = [];
        $sql = 'SELECT
                tc.constraint_name as name,
                tc.table_name as '.$this->field('table').',
                tc.table_schema as '.$this->field('schema').',
                kcu.column_name as '.$this->field('column').",
                ccu.table_schema AS foreign_schema,
                ccu.table_name AS foreign_table,
                ccu.column_name AS foreign_column,
                tc.constraint_type as type,
                rc.match_option,
                rc.update_rule,
                rc.delete_rule
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.key_column_usage kcu
                ON kcu.constraint_schema = tc.constraint_schema
                AND kcu.constraint_name = tc.constraint_name
                AND kcu.table_schema = tc.table_schema
                AND kcu.table_name = tc.table_name
            JOIN information_schema.constraint_column_usage AS ccu
                ON ccu.constraint_name = tc.constraint_name
            LEFT JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
            WHERE tc.CONSTRAINT_SCHEMA='{$schema}'";
        if ($table) {
            $sql .= "\nAND tc.table_name='{$table}'";
        }
        if ($type) {
            $sql .= "\nAND tc.constraint_type".($invertType ? '!=' : '=')."'{$type}'";
        }
        $sql .= ';';
        if ($result = $this->query($sql)) {
            while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                if ($constraint = ake($constraints, $row['name'])) {
                    if (!is_array($constraint['column'])) {
                        $constraint['column'] = [$constraint['column']];
                    }
                    if (!in_array($row['column'], $constraint['column'])) {
                        $constraint['column'][] = $row['column'];
                    }
                } else {
                    $constraint = [
                        'table' => $row['table'],
                        'column' => $row['column'],
                        'type' => $row['type'],
                    ];
                }
                foreach (['match_option', 'update_rule', 'delete_rule'] as $rule) {
                    if ($row[$rule]) {
                        $constraint[$rule] = $row[$rule];
                    }
                }
                if ('FOREIGN KEY' == $row['type'] && $row['foreign_table']) {
                    $constraint['references'] = [
                        'table' => $row['foreign_table'],
                        'column' => $row['foreign_column'],
                    ];
                }
                $constraints[$row['name']] = $constraint;
            }

            return $constraints;
        }

        return false;
    }

    /**
     * Retrieves a list of indexes for a given table or all tables in the specified schema.
     *
     * @param null|string $table The name of the table. If null, all tables in the schema will be considered.
     *
     * @return array<mixed>|false An array of indexes, where each index is represented by an associative array with the following keys:
     *                            - 'table': The name of the table the index belongs to.
     *                            - 'columns': An array of column names that make up the index.
     *                            - 'unique': A boolean indicating whether the index is unique or not.
     *
     * @throws \Hazaar\Exception if the index list retrieval fails
     */
    public function listIndexes(?string $table = null): array|false
    {
        if ($table) {
            list($schema, $table) = $this->parseSchemaName($table);
        } else {
            $schema = $this->schemaName;
        }
        $sql = "SELECT s.nspname, t.relname as table_name, i.relname as index_name, array_to_string(array_agg(a.attname), ', ') as column_names, ix.indisunique
            FROM pg_namespace s, pg_class t, pg_class i, pg_index ix, pg_attribute a
            WHERE s.oid = t.relnamespace
                AND t.oid = ix.indrelid
                AND i.oid = ix.indexrelid
                AND a.attrelid = t.oid
                AND a.attnum = ANY(ix.indkey)
                AND t.relkind = 'r'
                AND s.nspname = '{$schema}'";
        if ($table) {
            $sql .= "\nAND t.relname = '{$table}'";
        }
        $sql .= "\nGROUP BY s.nspname, t.relname, i.relname, ix.indisunique ORDER BY t.relname, i.relname;";
        if (!($result = $this->query($sql))) {
            throw new \Hazaar\Exception('Index list failed. '.$this->errorInfo()[2]);
        }
        $indexes = [];
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $indexes[$row['index_name']] = [
                'table' => $row['table_name'],
                'columns' => array_map('trim', explode(',', $row['column_names'])),
                'unique' => boolify($row['indisunique']),
            ];
        }

        return $indexes;
    }

    /**
     * Retrieves a list of views from the database.
     *
     * @return array<int, array<string>>|false an array of views, or null if no views are found
     */
    public function listViews(): array|false
    {
        $sql = 'SELECT table_schema as "schema", table_name as name FROM INFORMATION_SCHEMA.views WHERE ';
        if ('public' != $this->schemaName) {
            $sql .= "table_schema = '{$this->schemaName}'";
        } else {
            $sql .= "table_schema NOT IN ( 'information_schema', 'pg_catalog' )";
        }
        $sql .= ' ORDER BY table_name DESC;';
        if ($result = $this->query($sql)) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Retrieves the description of a database view.
     *
     * @param string $name the name of the view
     *
     * @return array<int, array<string>>|false the description of the view as an associative array, or null if the view does not exist
     */
    public function describeView($name): array|false
    {
        list($schema, $name) = $this->parseSchemaName($name);
        $sql = 'SELECT table_name as name, trim(view_definition) as content FROM INFORMATION_SCHEMA.views WHERE table_schema='
            .$this->prepareValue($schema).' AND table_name='.$this->prepareValue($name);
        if ($result = $this->query($sql)) {
            return $result->fetch(\PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Prepares the criteria action for the database query.
     *
     * @param string      $action the action to perform on the criteria
     * @param mixed       $value  the value to use in the criteria
     * @param string      $tissue the comparison operator for the criteria
     * @param null|string $key    the key to use in the criteria
     * @param bool        $setKey whether to set the key in the criteria
     *
     * @return string the prepared criteria action
     */
    public function prepareCriteriaAction($action, $value, $tissue = '=', $key = null, &$setKey = true): string
    {
        switch ($action) {
            case 'array':
                if (!is_array($value)) {
                    $value = [$value];
                }
                foreach ($value as &$val) {
                    $val = $this->prepareValue($val);
                }

                return 'ARRAY['.implode(',', $value).']';

            case 'push':
                if (!is_array($value)) {
                    $value = [$value];
                }
                foreach ($value as &$val) {
                    $val = $this->prepareValue($val);
                }

                return $this->field($key).' || ARRAY['.implode(',', $value).']';

            case 'any':
                $setKey = false;

                return $this->prepareValue($value)." {$tissue} ANY ({$key})";

            case 'all':
                $setKey = false;

                return $this->prepareValue($value)." {$tissue} ALL ({$key})";
        }

        return parent::prepareCriteriaAction($action, $value, $tissue, $key, $setKey);
    }

    /**
     * Retrieves a list of users from the PostgreSQL database.
     *
     * @return array<int, array<string>>|false an array containing the usernames of all the users in the database
     */
    public function listUsers(): array|false
    {
        $results = $this->query('SELECT usename FROM pg_catalog.pg_user;')->fetchAll(\PDO::FETCH_NUM);

        return array_column($results, 0);
    }

    /**
     * Retrieves a list of groups from the PostgreSQL database.
     *
     * @return array<int, array<string>>|false returns an array of group names
     */
    public function listGroups(): array|false
    {
        $results = $this->query('SELECT groname FROM pg_catalog.pg_group;')->fetchAll(\PDO::FETCH_NUM);

        return array_column($results, 0);
    }

    /**
     * Creates a new database role with the given name, password, and privileges.
     *
     * @param string        $name       the name of the role to create
     * @param string        $password   the password for the role (optional)
     * @param array<string> $privileges an array of privileges to assign to the role (optional)
     *
     * @return bool returns true if the role was created successfully, false otherwise
     */
    public function createRole(string $name, ?string $password = null, array $privileges = []): bool
    {
        $sql = 'CREATE ROLE '.$this->quoteSpecial($name).' WITH '.implode(' ', $privileges)." PASSWORD '{$password}';";

        return false !== $this->exec($sql);
    }

    /**
     * Retrieves a list of extensions in the specified schema.
     *
     * @return array<int, array<string>>|false an array containing the names of the extensions
     */
    public function listExtensions(): array|false
    {
        $results = $this->query('SELECT e.extname FROM pg_catalog.pg_extension e
            INNER JOIN pg_catalog.pg_namespace n ON e.extnamespace=n.oid
            WHERE n.nspname=\''.$this->schemaName.'\';')->fetchAll(\PDO::FETCH_NUM);

        return array_column($results, 0);
    }

    /**
     * Creates a PostgreSQL extension.
     *
     * @param string $name the name of the extension to create
     *
     * @return bool returns true if the extension was created successfully, false otherwise
     */
    public function createExtension($name): bool
    {
        $sql = 'CREATE EXTENSION IF NOT EXISTS '.$this->quoteSpecial($name).';';

        return false !== $this->exec($sql);
    }

    /**
     * Drops a PostgreSQL extension from the database.
     *
     * @param string $name     the name of the extension to drop
     * @param bool   $ifExists (optional) Whether to drop the extension only if it exists. Default is false.
     *
     * @return bool returns true if the extension was successfully dropped, false otherwise
     */
    public function dropExtension($name, $ifExists = false): bool
    {
        $sql = 'DROP EXTENSION ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->quoteSpecial($name).';';

        return false !== $this->exec($sql);
    }

    /**
     * Listens for notifications on a specific channel.
     *
     * This method establishes a connection to the PostgreSQL database and listens for notifications on the specified channel.
     *
     * @param string $channel the name of the channel to listen for notifications on
     *
     * @return bool returns true if the listen operation was successful, false otherwise
     */
    public function listen($channel): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return false !== pg_exec($this->conn, "LISTEN {$channel};");
    }

    /**
     * Unlistens from a PostgreSQL notification channel.
     *
     * This method establishes a connection to the PostgreSQL database and sends an UNLISTEN command to the specified channel.
     *
     * @param string $channel the name of the channel to unlisten from
     *
     * @return bool returns true if the unlisten command was successful, false otherwise
     */
    public function unlisten($channel): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return false !== pg_exec($this->conn, "UNLISTEN {$channel};");
    }

    /**
     * Sends a notification to a specific channel in the PostgreSQL database.
     *
     * @param string $channel the name of the channel to send the notification to
     * @param mixed  $payload (optional) The payload to include with the notification
     *
     * @return bool returns true if the notification was successfully sent, false otherwise
     */
    public function notify($channel, $payload = null): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return false !== pg_exec($this->conn, "NOTIFY {$channel}".($payload ? ', '.$this->quote($payload) : '').';');
    }

    /**
     * Retrieves the next asynchronous notification from the PostgreSQL server.
     *
     * @param int $mode The result format. Defaults to PGSQL_ASSOC.
     *
     * @return array<string,int|string>|false an associative array containing the notification message, or false if no notification is available
     */
    public function getNotify($mode = PGSQL_ASSOC): array|false
    {
        if (null === $this->conn) {
            return false;
        }

        return pg_get_notify($this->conn, $mode);
    }
}
