<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD;

use Hazaar\DBI\Table;
use Hazaar\Model;

class TestPDOStatement extends \PDOStatement
{
    /**
     * @var array<int, mixed>
     */
    private array $testData = [];
    private ?string $tableName = null;

    /**
     * @param array<int, mixed> $data
     */
    public function setTestData(string $tableName, array $data): void
    {
        $this->tableName = $tableName;
        $this->testData = $data;
    }

    public function columnCount(): int
    {
        return count($this->testData);
    }

    /**
     * @return array<string, mixed>
     */
    public function getColumnMeta(int $column): array
    {
        $columnName = array_keys($this->testData)[$column];
        $columnData = array_values($this->testData)[$column];
        $nativeType = gettype($columnData);
        $pdoType = match ($nativeType) {
            'integer' => \PDO::PARAM_INT,
            'boolean' => \PDO::PARAM_BOOL,
            default => \PDO::PARAM_STR,
        };

        return [
            'name' => $columnName,
            'table' => $this->tableName,
            'native_type' => $nativeType,
            'pdo_type' => $pdoType,
        ];
    }

    public function fetch(int $fetchStyle = \PDO::FETCH_BOTH, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->testData;
    }
}

class Dummy extends BaseDriver
{
    public ?string $lastQueryString = null;

    private int $testCount = 0;

    /**
     * @var array<int, mixed>
     */
    private array $rows = [];
    private ?string $tableName = null;

    public function connect(string $dsn, ?string $username = null, ?string $password = null, ?array $driverOptions = null): bool
    {
        return true;
    }

    public function setTimezone(string $tz): bool
    {
        return true;
    }

    public function repair(?string $table = null): bool
    {
        return true;
    }

    public function beginTransaction(): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollBack(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return true;
    }

    public function getAttribute(int $attribute): mixed
    {
        return $attribute;
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return true;
    }

    public function lastInsertId(): false|string
    {
        return 'TEST:'.++$this->testCount;
    }

    public function quote(mixed $string, int $type = \PDO::PARAM_STR): string
    {
        return "'{$string}'";
    }

    public function exec(string $sql): false|int
    {
        $this->lastQueryString = $sql;

        return preg_match('/^\s*(INSERT|UPDATE|DELETE|SELECT)\b/i', $sql) ? 1 : false;
    }

    public function query(string $sql): false|\PDOStatement
    {
        $this->lastQueryString = $sql;
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|SELECT)\b/i', $sql)) {
            $stmt = new TestPDOStatement();
            $stmt->queryString = $sql;
            if (null !== $this->tableName) {
                $stmt->setTestData($this->tableName, $this->rows);
            }

            return $stmt;
        }

        return false;
    }

    public function prepare(string $sql): false|\PDOStatement
    {
        return preg_match('/^\s*(INSERT|UPDATE|DELETE|SELECT)\b/i', $sql) ? new \PDOStatement() : false;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listTables(): array|false
    {
        return [];
    }

    public function insert(
        string $tableName,
        mixed $fields,
        mixed $returning = null,
        ?string $conflictTarget = null,
        mixed $conflictUpdate = null,
        ?Table $table = null
    ): false|int|\PDOStatement {
        $this->rows = $fields instanceof Model ? $fields->toArray(true) : (array) $fields;
        $this->tableName = $tableName;

        return parent::insert($tableName, $fields, $returning, $conflictTarget, $conflictUpdate, $table);
    }
}
