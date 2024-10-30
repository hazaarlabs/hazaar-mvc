<?php

declare(strict_types=1);

/**
 * Relational Database Driver namespace.
 */

namespace Hazaar\DBI\DBD;

use Hazaar\Date;
use Hazaar\DBI\Adapter;
use Hazaar\DBI\Table;
use Hazaar\Map;
use Hazaar\Model;

/**
 * Relational Database Driver - Base Class.
 */
abstract class BaseDriver implements Interfaces\Driver
{
    /**
     * @var array<string>
     */
    public static array $dsnElements = [];
    public ?string $lastQueryString = null;

    /**
     * @var array<string>
     */
    public static array $selectGroups = [];
    protected bool $allowConstraints = true;

    /**
     * @var array<string>
     */
    protected array $reservedWords = [];
    protected string $quoteSpecial = '"';

    protected Adapter $adapter;
    protected string $schemaName = 'main';

    protected static int $execs = 0;
    protected ?\PDO $pdo = null;
    private ?\Throwable $__lastError = null;

    public function __construct(Adapter $adapter, ?Map $config = null)
    {
        $this->adapter = $adapter;
        $this->schemaName = ake($config, 'dbname', 'public');
    }

    public function __toString()
    {
        return strtoupper(basename(str_replace('\\', DIRECTORY_SEPARATOR, get_class($this))));
    }

    public function setTimezone(string $tz): bool
    {
        return false;
    }

    public function execCount(): int
    {
        return BaseDriver::$execs;
    }

    public static function mkdsn(Map $config): false|string
    {
        $options = $config->toArray();
        $DBD = 'Hazaar\DBI\DBD\\'.ucfirst($config['driver']);
        if (!class_exists($DBD)) {
            return false;
        }
        $options = array_intersect_key($options, array_combine($DBD::$dsnElements, $DBD::$dsnElements));

        return $config['driver'].':'.array_flatten($options, '=', ';');
    }

    public function getSchemaName(): string
    {
        return $this->schemaName;
    }

    public function setSchemaName(string $schemaName): void
    {
        $this->schemaName = $schemaName;
    }

    public function schemaExists(string $schemaName): bool
    {
        return true;
    }

    public function createSchema(string $schemaName): bool
    {
        return false;
    }

    public function connect(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array $driverOptions = null
    ): bool {
        try {
            $this->pdo = new \PDO($dsn, $username, $password, $driverOptions);
        } catch (\Throwable $e) {
            $this->__lastError = $e;

            return false;
        }

        return true;
    }

    public function repair(?string $tableName = null): bool
    {
        return true;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->pdo->getAttribute($attribute);
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(): false|string
    {
        return $this->pdo->lastInsertId();
    }

    public function quote(mixed $string, int $type = \PDO::PARAM_STR): false|string
    {
        if (is_string($string)) {
            $string = $this->pdo->quote($string, $type);
        }

        return $string;
    }

    public function quoteSpecial(mixed $value): mixed
    {
        if (false === is_string($value)) {
            return $value;
        }
        $parts = explode('.', $value);
        array_walk($parts, function (&$item) {
            $item = $this->quoteSpecial.$item.$this->quoteSpecial;
        });

        return implode('.', $parts);
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollback();
    }

    public function errorCode(): mixed
    {
        return $this->pdo->errorCode();
    }

    /**
     * @return array<int,string>
     */
    public function errorInfo(): array
    {
        if (null !== $this->__lastError) {
            return [
                $this->__lastError->getCode(),
                $this->__lastError->getMessage(),
                '',
            ];
        }

        return $this->pdo->errorInfo();
    }

    public function exec(string $sql): false|int
    {
        $sql = rtrim($sql, '; ').';';

        return $this->pdo->exec($sql);
    }

    public function query(string $sql): false|\PDOStatement
    {
        $sql = rtrim($sql, '; ').';';

        return $this->pdo->query($sql);
    }

    public function prepare(string $sql): false|\PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    public function schemaName(string $tableName): string
    {
        $alias = null;
        // Check if there is an alias
        if (($pos = strpos($tableName, ' ')) !== false) {
            list($tableName, $alias) = preg_split('/\s*(?<=.{'.$pos.'})\s*/', $tableName, 2);
        }
        // Check if we already have a schemaName defined
        if (false === strpos($tableName, '.')) {
            $tableName = $this->schemaName.'.'.$tableName;
        }

        return $this->quoteSpecial($tableName).($alias ? ' '.$this->quoteSpecial($alias) : '');
    }

    /**
     * @return array<int,string>
     */
    public function parseSchemaName(string $tableName): array
    {
        $schemaName = $this->schemaName;
        if (false !== strpos($tableName, '.')) {
            list($schema, $tableName) = explode('.', $tableName);
        }

        return [$schemaName, $tableName];
    }

    public function field(string $string): string
    {
        if (in_array(strtoupper($string), $this->reservedWords)) {
            $string = $this->quoteSpecial($string);
        }

        return $string;
    }

    /**
     * @param array<string> $info
     */
    public function type(array $info): false|string
    {
        if (!($type = ake($info, 'data_type'))) {
            return false;
        }
        if ($array = ('[]' === substr($type, -2))) {
            $type = substr($type, 0, -2);
        }

        return $type.(ake($info, 'length') ? '('.$info['length'].')' : null).($array ? '[]' : '');
    }

    /**
     * @param array<mixed> $criteria
     */
    public function prepareCriteria(
        array|string $criteria,
        ?string $bindType = null,
        ?string $tissue = null,
        ?string $parentRef = null,
        ?string $optionalKey = null,
        bool &$setKey = true
    ): string {
        if (!is_array($criteria)) {
            return $criteria;
        }
        $parts = [];
        if (0 === count($criteria)) {
            return 'TRUE';
        }
        if (null === $bindType) {
            $bindType = 'AND';
        }
        if (null === $tissue) {
            $tissue = '=';
        }
        foreach ($criteria as $key => $value) {
            if ($value instanceof Table) {
                $value = '('.$value->toString().' )';
            }
            if (is_int($key) && is_string($value)) {
                $parts[] = '('.$value.')';
            } elseif ('$' == substr($key, 0, 1)) {
                if ($actionParts = $this->prepareCriteriaAction(strtolower(substr($key, 1)), $value, $tissue, $optionalKey, $setKey)) {
                    if (is_array($actionParts)) {
                        $parts = array_merge($parts, $actionParts);
                    } else {
                        $parts[] = $actionParts;
                    }
                } else {
                    $parts[] = ' '.$tissue.' '.$this->prepareCriteria($value, strtoupper(substr($key, 1)));
                }
            } else {
                if (is_array($value)) {
                    $set = true;
                    $subValue = $this->prepareCriteria($value, $bindType, $tissue, $parentRef, $key, $set);
                    if (is_numeric($key)) {
                        $parts[] = $subValue;
                    } else {
                        if ($parentRef && false === strpos($key, '.')) {
                            $key = $parentRef.'.'.$key;
                        }
                        $parts[] = ((true === $set) ? $this->field($key).' ' : '').$subValue;
                    }
                } else {
                    if ($parentRef && false === strpos($key, '.')) {
                        $key = $parentRef.'.'.$key;
                    }
                    if (is_null($value) || is_boolean($value)) {
                        $joiner = 'IS'.(('!=' === $tissue) ? 'NOT' : null);
                    } else {
                        $joiner = $tissue;
                    }
                    $parts[] = $this->field($key).' '.$joiner.' '.$this->prepareValue($value);
                }
            }
        }
        $sql = '';
        // @phpstan-ignore-next-line
        if (count($parts) > 0) {
            $sql = ((count($parts) > 1) ? '(' : null).implode(" {$bindType} ", $parts).((count($parts) > 1) ? ')' : null);
        }

        return $sql;
    }

    /**
     * @return null|array<string>|string
     */
    public function prepareCriteriaAction(
        string $action,
        mixed $value,
        string $tissue = '=',
        ?string $key = null,
        bool &$setKey = true
    ): null|array|string {
        switch ($action) {
            case 'and':
                return $this->prepareCriteria($value, 'AND');

            case 'or':
                return $this->prepareCriteria($value, 'OR');

            case 'ne':
                if (is_null($value)) {
                    return 'IS NOT NULL';
                }

                return (is_bool($value) ? 'IS NOT ' : '!= ').$this->prepareValue($value);

            case 'not':
                return 'NOT ('.$this->prepareCriteria($value).')';

            case 'ref':
                return $tissue.' '.$value;

            case 'nin':
            case 'in':
                if (is_array($value)) {
                    if (0 === count($value)) {
                        throw new \Exception('$in requires non-empty array value');
                    }
                    $values = [];
                    foreach ($value as $val) {
                        $values[] = $this->prepareValue($val);
                    }
                    $value = implode(', ', $values);
                }

                return (('nin' == $action) ? 'NOT ' : null).'IN ('.$value.')';

            case 'gt':
                return '> '.$this->prepareValue($value);

            case 'gte':
                return '>= '.$this->prepareValue($value);

            case 'lt':
                return '< '.$this->prepareValue($value);

            case 'lte':
                return '<= '.$this->prepareValue($value);

            case 'ilike': // iLike
                return 'ILIKE '.$this->quote($value);

            case 'like': // Like
                return 'LIKE '.$this->quote($value);

            case 'bt':
                if (($count = count($value)) !== 2) {
                    throw new \Exception('DBD: $bt operator requires array argument with exactly 2 elements. '.$count.' given.');
                }

                return 'BETWEEN '.$this->prepareValue(array_values($value)[0])
                    .' AND '.$this->prepareValue(array_values($value)[1]);

            case '~':
            case '~*':
            case '!~':
            case '!~*':
                return $action.' '.$this->quote($value);

            case 'exists': // exists
                $parts = [];
                foreach ($value as $table => $criteria) {
                    $parts[] = 'EXISTS ( SELECT * FROM '.$this->schemaName($table).' WHERE '.$this->prepareCriteria($criteria).' )';
                }

                return $parts;

            case 'sub': // sub query
                return '('.$value[0]->toString(false).') '.$this->prepareCriteria($value[1]);

            case 'json':
                return $this->prepareValue(json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return null;
    }

    /**
     * @param array<string> $exclude
     * @param array<string> $tables
     */
    public function prepareFields(mixed $fields, array $exclude = [], array $tables = []): string
    {
        if (!is_array($fields)) {
            return $this->field($fields);
        }
        if (!is_array($exclude)) {
            $exclude = [];
        }
        $fieldDef = [];
        foreach ($fields as $key => $value) {
            if ($value instanceof Table) {
                $value = ((1 === $value->limit()) ? '(' : 'array(').$value.')';
            }
            if (is_string($value) && in_array($value, $exclude)) {
                $fieldDef[] = $value;
            } elseif (is_numeric($key)) {
                $fieldDef[] = is_array($value) ? $this->prepareFields($value, [], $tables) : $this->field($value);
            } elseif (is_array($value)) {
                $fields = [];
                $fieldMap = array_to_dot_notation([$key => $this->prepareArrayAliases($value)]);
                foreach ($fieldMap as $alias => $field) {
                    if (preg_match('/^((\w+)\.)?\*$/', trim($field), $matches) > 0) {
                        if (count($matches) > 1) {
                            $alias = ake($tables, $matches[2]);
                        } else {
                            $alias = reset($tables);
                            $value = key($tables).'.*';
                        }
                        self::$selectGroups[$alias] = $key;
                        $fieldDef[] = $this->field($field);

                        continue;
                    }
                    $lookup = md5(uniqid('dbi_', true));
                    self::$selectGroups[$lookup] = $alias;
                    $fields[$lookup] = $field;
                }
                $fieldDef[] = $this->prepareFields($fields, [], $tables);
            } elseif (preg_match('/^((\w+)\.)?\*$/', trim($value), $matches) > 0) {
                if (count($matches) > 1) {
                    $alias = ake($tables, $matches[2]);
                } else {
                    $alias = reset($tables);
                    $value = key($tables).'.*';
                }
                self::$selectGroups[$alias] = $key;
                $fieldDef[] = $this->field($value);
            } else {
                $fieldDef[] = $this->field($value).' AS '.$this->field($key);
            }
        }

        return implode(', ', $fieldDef);
    }

    public function prepareValues(mixed $values): string
    {
        if (!is_array($values)) {
            $values = [$values];
        }
        foreach ($values as &$value) {
            $value = $this->prepareValue($value);
        }

        return implode(',', $values);
    }

    public function prepareValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value) && count($value) > 0) {
            $value = $this->prepareCriteria($value, null, null, null, $key);
        } elseif ($value instanceof Date) {
            $value = $this->quote($value->format('Y-m-d H:i:s'));
        } elseif (is_null($value) || (is_array($value) && 0 === count($value))) {
            $value = 'NULL';
        } elseif (is_bool($value)) {
            $value = ($value ? 'TRUE' : 'FALSE');
        } elseif ($value instanceof \stdClass) {
            $value = $this->quote(json_encode($value));
        } elseif (!is_int($value) && (':' !== substr($value, 0, 1) || ':' === substr($value, 1, 1))) {
            $value = $this->quote((string) $value);
        }

        return $value;
    }

    public function insert(
        string $tableName,
        mixed $fields,
        null|array|bool|string $returning = null,
        null|array|string $conflictTarget = null,
        mixed $conflictUpdate = null,
        ?Table $table = null
    ): false|int|\PDOStatement {
        $sql = 'INSERT INTO '.$this->schemaName($tableName);
        if ($fields instanceof Map) {
            $fields = $fields->toArray();
        } elseif ($fields instanceof Model) {
            $fields = $fields->toArray(true);
        }
        if ($fields instanceof \stdClass) {
            $fields = (array) $fields;
        } elseif ($fields instanceof Table) {
            $sql .= ' '.(string) $fields;
        } elseif ($table instanceof Table) {
            $fieldDef = [];
            foreach ($fields as $key => $fieldName) {
                $fieldDef[] = $this->field($fieldName);
            }
            $sql .= ' ('.implode(', ', $fieldDef).' ) '.(string) $table->toString(false, true);
        } else {
            $fieldDef = array_keys($fields);
            foreach ($fieldDef as &$field) {
                $field = $this->field($field);
            }
            $valueDef = array_values($fields);
            foreach ($valueDef as $key => &$value) {
                $value = $this->prepareValue($value, $fieldDef[$key]);
            }
            $sql .= ' ('.implode(', ', $fieldDef).') VALUES ('.implode(', ', $valueDef).')';
        }
        if (null !== $conflictTarget) {
            $sql .= ' ON CONFLICT('.$this->prepareFields($conflictTarget).')';
            if (null === $conflictUpdate) {
                $sql .= ' DO NOTHING';
            } else {
                if (true === $conflictUpdate) {
                    $conflictUpdate = array_keys($fields);
                }
                if (is_array($conflictUpdate) && count($conflictUpdate) > 0) {
                    $updateDefs = [];
                    foreach ($conflictUpdate as $index => $field) {
                        if (is_int($index)) {
                            if (!array_key_exists($field, $fields) || $field === $conflictTarget) {
                                continue;
                            }
                            $updateDefs[] = $this->field($field).' = EXCLUDED.'.$field;
                        } else {
                            $updateDefs[] = $this->field($index).' = '.$field;
                        }
                    }
                    $sql .= ' DO UPDATE SET '.implode(', ', $updateDefs);
                }
            }
        }
        $returnValue = false;
        if (null === $returning || false === $returning || (is_array($returning) && 0 === count($returning))) {
            $returnValue = $this->exec($sql);
        } elseif (true === $returning) {
            if ($result = $this->query($sql)) {
                $returnValue = (int) $this->lastinsertid();
            }
        } else {
            if (is_string($returning)) {
                $returning = trim($returning);
                $sql .= ' RETURNING '.$this->field($returning);
            } elseif (is_array($returning)) {
                $sql .= ' RETURNING '.$this->prepareFields($returning);
            }
            if ($result = $this->query($sql)) {
                $returnValue = (is_string($returning) && '*' !== $returning) ? $result->fetchColumn(0) : $result;
            }
        }

        return $returnValue;
    }

    /**
     * @param null|array<string>|bool|string $returning
     */
    public function update(
        string $tableName,
        mixed $fields,
        array $criteria = [],
        array $from = [],
        null|array|bool|string $returning = null,
        array $tables = []
    ): false|int|\PDOStatement {
        if ($fields instanceof Map) {
            $fields = $fields->toArray();
        } elseif ($fields instanceof Model) {
            $fields = $fields->toArray(true);
        } elseif ($fields instanceof \stdClass) {
            $fields = (array) $fields;
        }
        $fieldDef = [];
        foreach ($fields as $key => &$value) {
            $fieldDef[] = $this->field($key).' = '.$this->prepareValue($value, $key);
        }
        if (0 == count($fieldDef)) {
            throw new Exception\NoUpdate();
        }
        $sql = 'UPDATE '.$this->schemaName($tableName).' SET '.implode(', ', $fieldDef);
        if (is_array($from) && count($from) > 0) {
            $sql .= ' FROM '.implode(', ', $from);
        }
        if (is_array($criteria) && count($criteria) > 0) {
            $sql .= ' WHERE '.$this->prepareCriteria($criteria);
        }
        $returnValue = false;
        if (true === $returning) {
            $returning = '*';
        }
        if (null === $returning || false === $returning || (is_array($returning) && 0 === count($returning))) {
            $returnValue = $this->exec($sql);
        } else {
            if (is_string($returning)) {
                $returning = trim($returning);
                $sql .= ' RETURNING '.$this->field($returning);
            } elseif (is_array($returning)) {
                $sql .= ' RETURNING '.$this->prepareFields($returning, [], $tables);
            }
            if ($result = $this->query($sql)) {
                $returnValue = (is_string($returning) && !('*' === $returning || strpos($returning, ','))) ? $result->fetchColumn(0) : $result;
            }
        }

        return $returnValue;
    }

    /**
     * @param array<string> $from
     */
    public function delete(string $tableName, mixed $criteria, array $from = []): false|int
    {
        $sql = 'DELETE FROM '.$this->schemaName($tableName);
        if (count($from) > 0) {
            $sql .= ' USING '.$this->prepareFields($from);
        }
        $sql .= ' WHERE '.$this->prepareCriteria($criteria);

        return $this->exec($sql);
    }

    public function deleteAll(string $tableName): false|int
    {
        return $this->exec('DELETE FROM '.$this->schemaName($tableName));
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listTables(): array|false
    {
        return false;
    }

    public function tableExists(string $tableName): bool
    {
        $stmt = $this->query('SELECT EXISTS(SELECT * FROM information_schema.tables WHERE table_name='
            .$this->quote($tableName).' AND table_schema='
            .$this->quote($this->schemaName).');');
        if ($stmt instanceof \PDOStatement) {
            return $stmt->fetchColumn(0);
        }

        throw new \Exception('tableExists: '.ake($this->errorInfo(), 2));
    }

    public function createTable(string $tableName, mixed $columns): bool
    {
        $sql = 'CREATE TABLE '.$this->schemaName($tableName)." (\n";
        $coldefs = [];
        $constraints = [];
        foreach ($columns as $name => $info) {
            if (is_array($info)) {
                if (is_numeric($name)) {
                    if (!array_key_exists('name', $info)) {
                        throw new \Exception('Error creating new table.  Name is a number which is not allowed!');
                    }
                    $name = $info['name'];
                }
                if (!($type = $this->type($info))) {
                    throw new \Exception("Column '{$name}' has no data type!");
                }
                $def = $this->field($name).' '.$type;
                if (array_key_exists('default', $info) && null !== $info['default']) {
                    $def .= ' DEFAULT '.$info['default'];
                }
                if (array_key_exists('not_null', $info) && $info['not_null']) {
                    $def .= ' NOT NULL';
                }
                if (array_key_exists('primarykey', $info) && $info['primarykey']) {
                    $driver = strtolower(basename(str_replace('\\', '/', get_class($this))));
                    if ('pgsql' == $driver) {
                        $constraints[] = ' PRIMARY KEY('.$this->field($name).')';
                    } else {
                        $def .= ' PRIMARY KEY';
                    }
                }
            } else {
                $def = "\t".$this->field($name).' '.$info;
            }
            $coldefs[] = $def;
        }
        $sql .= implode(",\n", $coldefs);
        if (count($constraints) > 0) {
            $sql .= ",\n".implode(",\n", $constraints);
        }
        $sql .= "\n);";
        $affected = $this->exec($sql);
        if (false === $affected) {
            throw new \Exception("Could not create table '{$tableName}'. ".$this->errorInfo()[2]);
        }

        return true;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeTable(string $tableName, ?string $sort = null): array|false
    {
        if (!$sort) {
            $sort = 'ordinal_position';
        }
        $result = $this->query('SELECT * FROM information_schema.columns WHERE table_name='
            .$this->quote($tableName).' AND table_schema='
            .$this->quote($this->schemaName).' ORDER BY '
            .$sort);
        if (false === $result) {
            throw new \Exception(ake($this->errorInfo(), 2));
        }
        $columns = [];
        while ($col = $result->fetch(\PDO::FETCH_ASSOC)) {
            $col = array_change_key_case($col, CASE_LOWER);
            if (array_key_exists('column_default', $col)
                && $col['column_default']
                && preg_match('/nextval\(\'(\w*)\'::regclass\)/', $col['column_default'], $matches)) {
                if ($info = $this->describeSequence($matches[1])) {
                    $col['data_type'] = 'serial';
                    $col['column_default'] = null;
                    $col['sequence'] = $info;
                }
            }
            // Fixed array types to their actual SQL array data type
            if ('ARRAY' == $col['data_type']
                && ($udtName = ake($col, 'udt_name'))) {
                if ('_' == $udtName[0]) {
                    $col['data_type'] = substr($udtName, 1).'[]';
                }
            }
            $coldata = [
                'name' => $col['column_name'],
                'default' => $this->fixValue($col['column_default']),
                'not_null' => (('NO' == $col['is_nullable']) ? true : false),
                'data_type' => $this->type($col),
                'length' => $col['character_maximum_length'],
            ];
            if (array_key_exists('sequence', $col)) {
                $coldata['sequence'] = $col['sequence']['sequence_schema'].'.'.$col['sequence']['sequence_name'];
            }
            $columns[] = $coldata;
        }

        return $columns;
    }

    public function renameTable(string $fromName, string $toName): bool
    {
        if (strpos($toName, '.')) {
            list($fromSchemaName, $fromName) = explode('.', $fromName);
            list($toSchemaName, $toName) = explode('.', $toName);
            if ($toSchemaName != $fromSchemaName) {
                throw new \Exception('You can not rename tables between schemas!');
            }
        }
        $sql = 'ALTER TABLE '.$this->schemaName($fromName).' RENAME TO '.$this->field($toName).';';
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function dropTable(string $name, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'DROP TABLE ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->schemaName($name).($cascade ? ' CASCADE' : '').';';
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function addColumn(string $tableName, mixed $columnSpec): bool
    {
        if (!array_key_exists('name', $columnSpec)) {
            return false;
        }
        if (!array_key_exists('data_type', $columnSpec)) {
            return false;
        }
        $sql = 'ALTER TABLE '.$this->schemaName($tableName).' ADD COLUMN '.$this->field($columnSpec['name']).' '.$this->type($columnSpec);
        if (array_key_exists('not_null', $columnSpec) && $columnSpec['not_null']) {
            $sql .= ' NOT NULL';
        }
        if (array_key_exists('default', $columnSpec) && null !== $columnSpec['default']) {
            $sql .= ' DEFAULT '.$columnSpec['default'];
        }
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function alterColumn(string $tableName, string $column, mixed $columnSpec): bool
    {
        $sqls = [];
        // Check if the column is being renamed and update the name first.
        if (array_key_exists('name', $columnSpec)) {
            $sql = 'ALTER TABLE '.$this->schemaName($tableName).' RENAME COLUMN '.$this->field($column).' TO '.$this->field($columnSpec['name']);
            $this->exec($sql);
            $column = $columnSpec['name'];
        }
        $prefix = 'ALTER TABLE '.$this->schemaName($tableName).' ALTER COLUMN '.$this->field($column);
        if (array_key_exists('data_type', $columnSpec)) {
            $alterType = $prefix.' TYPE '.$this->type($columnSpec);
            if (array_key_exists('using', $columnSpec)) {
                $alterType .= ' USING '.$columnSpec['using'];
            }
            $sqls[] = $alterType;
        }
        if (array_key_exists('not_null', $columnSpec)) {
            $sqls[] = $prefix.' '.($columnSpec['not_null'] ? 'SET' : 'DROP').' NOT NULL';
        }
        if (array_key_exists('default', $columnSpec)) {
            $sqls[] = $prefix.' '.(null === $columnSpec['default']
                ? 'DROP DEFAULT'
                : 'SET DEFAULT '.$columnSpec['default']);
        }
        foreach ($sqls as $sql) {
            $this->exec($sql);
        }

        return true;
    }

    public function dropColumn(string $tableName, string $column, bool $ifExists = false): bool
    {
        $sql = 'ALTER TABLE ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->schemaName($tableName).' DROP COLUMN ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->field($column).';';
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listSequences(): array|false
    {
        $result = $this->query("SELECT sequence_schemaName as schema, sequence_name as name
            FROM information_schema.sequences
            WHERE sequence_schemaName NOT IN ('information_schema', 'pg_catalog');");

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeSequence(string $name): array|false
    {
        $sql = "SELECT * FROM information_schema.sequences WHERE sequence_name = '{$name}'";
        if ($this->schemaName) {
            $sql .= " AND sequence_schemaName = '{$this->schemaName}'";
        }
        $result = $this->query($sql);

        return $result->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<mixed>|false
     */
    public function listIndexes(?string $tableName = null): array|false
    {
        return false;
    }

    public function createIndex(string $indexName, string $tableName, mixed $idxInfo): bool
    {
        if (!array_key_exists('columns', $idxInfo)) {
            return false;
        }
        $indexes = $this->listIndexes($tableName);
        if (array_key_exists($indexName, $indexes)) {
            return true;
        }
        $sql = 'CREATE';
        if (array_key_exists('unique', $idxInfo) && $idxInfo['unique']) {
            $sql .= ' UNIQUE';
        }
        $sql .= ' INDEX '.$this->field($indexName).' ON '.$this->schemaName($tableName).' ('.implode(',', array_map([$this, 'field'], $idxInfo['columns'])).')';
        if (array_key_exists('using', $idxInfo) && $idxInfo['using']) {
            $sql .= ' USING '.$idxInfo['using'];
        }
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function dropIndex(string $indexName, bool $ifExists = false): bool
    {
        $sql = 'DROP INDEX ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->field($indexName);
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listConstraints(?string $tableName = null, ?string $type = null, bool $invertType = false): array|false
    {
        return false;
    }

    public function addConstraint(string $constraintName, mixed $info): bool
    {
        if (!$this->allowConstraints) {
            return false;
        }
        if (!array_key_exists('table', $info)) {
            return false;
        }
        if (!array_key_exists('type', $info) || !$info['type']) {
            if (array_key_exists('references', $info)) {
                $info['type'] = 'FOREIGN KEY';
            } else {
                return false;
            }
        }
        if ('FOREIGN KEY' == $info['type']) {
            if (!array_key_exists('update_rule', $info)) {
                $info['update_rule'] = 'NO ACTION';
            }
            if (!array_key_exists('delete_rule', $info)) {
                $info['delete_rule'] = 'NO ACTION';
            }
        }
        $column = $info['column'];
        if (is_array($column)) {
            foreach ($column as &$col) {
                $col = $this->field($col);
            }
            $column = implode(', ', $column);
        } else {
            $column = $this->field($column);
        }
        $sql = 'ALTER TABLE '.$this->schemaName($info['table']).' ADD CONSTRAINT '.$this->field($constraintName)." {$info['type']} (".$column.')';
        if (array_key_exists('references', $info)) {
            $sql .= ' REFERENCES '.$this->schemaName($info['references']['table']).' ('.$this->field($info['references']['column']).") ON UPDATE {$info['update_rule']} ON DELETE {$info['delete_rule']}";
        }
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function dropConstraint(string $constraintName, string $tableName, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'ALTER TABLE ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->schemaName($tableName).' DROP CONSTRAINT ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->field($constraintName).($cascade ? ' CASCADE' : '');
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listViews(): array|false
    {
        return false;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeView(string $name): array|false
    {
        return false;
    }

    public function createView(string $name, mixed $content): bool
    {
        $sql = 'CREATE OR REPLACE VIEW '.$this->schemaName($name).' AS '.rtrim($content, ' ;');

        return false !== $this->exec($sql);
    }

    public function viewExists(string $viewName): bool
    {
        $views = $this->listViews();

        return false !== $views && in_array($viewName, $views);
    }

    public function dropView(string $name, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'DROP VIEW ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->schemaName($name);
        if (true === $cascade) {
            $sql .= ' CASCADE';
        }

        return false !== $this->exec($sql);
    }

    /**
     * List defined functions.
     *
     * @return array<int,array<mixed>|string>|false
     */
    public function listFunctions(?string $schemaName = null, bool $includeParameters = false): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->schemaName;
        }
        $sql = "SELECT r.specific_name, 
                r.routine_schema, 
                r.routine_name, 
                p.data_type 
            FROM INFORMATION_SCHEMA.routines r 
            LEFT JOIN INFORMATION_SCHEMA.parameters p ON p.specific_name=r.specific_name
            WHERE r.routine_type='FUNCTION'
            AND r.specific_schema=".$this->prepareValue($schemaName)."
            AND NOT (r.routine_body='EXTERNAL' AND r.external_language='C')
            ORDER BY r.routine_name, p.ordinal_position;";
        $q = $this->query($sql);
        $list = [];
        while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
            $id = $includeParameters ? $row['specific_name'] : $row['routine_schema'].$row['routine_name'];
            if (true !== $includeParameters) {
                if (!array_key_exists($id, $list)) {
                    $list[$id] = $row['routine_name'];
                }

                continue;
            }
            if (!array_key_exists($id, $list)) {
                $list[$id] = [
                    'name' => $row['routine_name'],
                    'parameters' => [],
                ];
            }
            if ($row['data_type']) {
                $list[$id]['parameters'][] = $row['data_type'];
            }
        }

        return array_values($list);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeFunction(string $name, ?string $schemaName = null): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->schemaName;
        }
        $sql = "SELECT r.specific_name,
                    r.routine_schema,
                    r.routine_name,
                    r.data_type AS return_type,
                    r.routine_body,
                    r.routine_definition,
                    r.external_language,
                    p.parameter_name,
                    p.data_type,
                    p.parameter_mode,
                    p.ordinal_position
                FROM INFORMATION_SCHEMA.routines r
                LEFT JOIN INFORMATION_SCHEMA.parameters p ON p.specific_name=r.specific_name
                WHERE r.routine_type='FUNCTION'
                AND r.routine_schema=".$this->prepareValue($schemaName).'
                AND r.routine_name='.$this->prepareValue($name).'
                ORDER BY r.routine_name, p.ordinal_position;';
        if (!($q = $this->query($sql))) {
            throw new \Exception($this->errorInfo()[2]);
        }
        $info = [];
        while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
            if (!array_key_exists($row['specific_name'], $info)) {
                if (!($routineDefinition = ake($row, 'routine_definition'))) {
                    continue;
                }
                $item = [
                    'name' => $row['routine_name'],
                    'return_type' => $row['return_type'],
                    'content' => trim($routineDefinition),
                ];
                $item['parameters'] = [];
                $item['lang'] = ('EXTERNAL' === strtoupper($row['routine_body']))
                    ? $row['external_language']
                    : $row['routine_body'];
                $info[$row['specific_name']] = $item;
            }
            if (null === $row['parameter_name']) {
                continue;
            }
            $info[$row['specific_name']]['parameters'][] = [
                'name' => $row['parameter_name'],
                'type' => $row['data_type'],
                'mode' => $row['parameter_mode'],
                'ordinal_position' => $row['ordinal_position'],
            ];
        }
        usort($info, function ($a, $b) {
            if (count($a['parameters']) === count($b['parameters'])) {
                return 0;
            }

            return count($a['parameters']) < count($b['parameters']) ? -1 : 1;
        });

        return $info;
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
        $sql = 'CREATE OR REPLACE FUNCTION '.$this->schemaName($name).' (';
        if ($params = ake($spec, 'parameters')) {
            $items = [];
            foreach ($params as $param) {
                $items[] = ake($param, 'mode', 'IN').' '.ake($param, 'name').' '.ake($param, 'type');
            }
            $sql .= implode(', ', $items);
        }
        $sql .= ') RETURNS '.ake($spec, 'return_type', 'TEXT').' LANGUAGE '.ake($spec, 'lang', 'SQL')." AS\n\$BODY$ ";
        $sql .= ake($spec, 'content');
        $sql .= '$BODY$;';

        return false !== $this->exec($sql);
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
        $sql = 'DROP FUNCTION ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->schemaName($name);
        if (null !== $argTypes) {
            $sql .= '('.(is_array($argTypes) ? implode(', ', $argTypes) : $argTypes).')';
        }
        if (true === $cascade) {
            $sql .= ' CASCADE';
        }

        return false !== $this->exec($sql);
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
        $sql = 'TRUNCATE TABLE '.($only ? 'ONLY ' : '').$this->schemaName($tableName);
        $sql .= ' '.($restartIdentity ? 'RESTART IDENTITY' : 'CONTINUE IDENTITY');
        $sql .= ' '.($cascade ? 'CASCADE' : 'RESTRICT');

        return false !== $this->exec($sql);
    }

    /**
     * List defined triggers.
     *
     * @param string $schemaName Optional: schema name.  If not supplied the current schemaName is used.
     *
     * @return array<int,array<string>>|false
     */
    public function listTriggers(?string $tableName = null, ?string $schemaName = null): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->schemaName;
        }
        $sql = 'SELECT DISTINCT trigger_schemaName AS schema, trigger_name AS name
                    FROM INFORMATION_SCHEMA.triggers
                    WHERE event_object_schema='.$this->prepareValue($schemaName);
        if (null !== $tableName) {
            $sql .= ' AND event_object_table='.$this->prepareValue($tableName);
        }
        if ($result = $this->query($sql)) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Describe a database trigger.
     *
     * This will return an array as there can be multiple triggers with the same name but with different attributes
     *
     * @param string $schemaName Optional: schemaName name.  If not supplied the current schemaName is used.
     *
     * @return array<int, array<string>>|false
     */
    public function describeTrigger(string $triggerName, ?string $schemaName = null): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->schemaName;
        }
        $sql = 'SELECT trigger_name AS triggerName,
                        event_manipulation AS events,
                        event_object_table AS table,
                        action_statement AS content,
                        action_orientation AS orientation,
                        action_timing AS timing
                    FROM INFORMATION_SCHEMA.triggers
                    WHERE trigger_schema='.$this->prepareValue($schemaName)
                    .' AND trigger_name='.$this->prepareValue($triggerName);
        if (!($result = $this->query($sql))) {
            return false;
        }
        $info = $result->fetch(\PDO::FETCH_ASSOC);
        $info['events'] = [$info['events']];
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $info['events'][] = $row['events'];
        }

        return $info;
    }

    /**
     * Summary of createTrigger.
     *
     * @param string $tableName The table on which the trigger is being created
     * @param mixed  $spec      The spec of the trigger.  Basically this is the array returned from describeTriggers()
     */
    public function createTrigger(string $triggerName, string $tableName, mixed $spec = []): bool
    {
        $sql = 'CREATE TRIGGER '.$this->field($triggerName)
            .' '.ake($spec, 'timing', 'BEFORE')
            .' '.implode(' OR ', ake($spec, 'events', ['INSERT']))
            .' ON '.$this->schemaName($tableName)
            .' FOR EACH '.ake($spec, 'orientation', 'ROW')
            .' '.ake($spec, 'content', 'EXECUTE');

        return false !== $this->exec($sql);
    }

    /**
     * Drop a trigger from a table.
     *
     * @param string $tableName The name of the table to remove the trigger from
     * @param bool   $cascade   Whether to drop CASCADE
     */
    public function dropTrigger(string $triggerName, string $tableName, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'DROP TRIGGER ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->field($triggerName).' ON '.$this->schemaName($tableName);
        $sql .= ' '.((true === $cascade) ? ' CASCADE' : ' RESTRICT');

        return false !== $this->exec($sql);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listUsers(): array|false
    {
        return false;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listGroups(): array|false
    {
        return false;
    }

    /**
     * @param array<string> $privileges
     */
    public function createRole(string $name, ?string $password = null, array $privileges = []): bool
    {
        return false;
    }

    public function dropRole(string $name, bool $ifExists = false): bool
    {
        return false;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listExtensions(): array|false
    {
        return false;
    }

    public function createExtension(string $name): bool
    {
        return false;
    }

    public function dropExtension(string $name, bool $ifExists = false): bool
    {
        return false;
    }

    public function createDatabase(string $name): bool
    {
        $sql = 'CREATE DATABASE '.$this->quoteSpecial($name).';';
        $result = $this->query($sql);

        return true;
    }

    /**
     * Special internal function to fix the default column value.
     *
     * This function is normally overridden by the DBD class being used so that values can be "fixed".
     */
    protected function fixValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    private function prepareArrayAliases(array $array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->prepareArrayAliases($value);
            } elseif (is_string($value) && '*' === substr($value, -1)) {
                continue;
            }
            if (!is_numeric($key)) {
                continue;
            }
            unset($array[$key]);
            $key = $value;
            if (($pos = strrpos($key, '.')) > 0) {
                $key = substr($key, $pos + 1);
            }
            $array[$key] = $value;
        }

        return $array;
    }
}
