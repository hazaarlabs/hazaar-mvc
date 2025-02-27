<?php

namespace Hazaar\DBI\DBD\Traits\SQL;

trait Schema
{
    public function getSchemaName(): string
    {
        return $this->schemaName;
    }

    /**
     * Checks if a schema exists in the database.
     *
     * @param string $schemaName the name of the schema to check
     *
     * @return bool returns true if the schema exists, false otherwise
     */
    public function schemaExists(?string $schemaName = null): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        if (!$schemaName) {
            $schemaName = $this->getSchemaName();
        }
        $sql = $queryBuilder->exists('information_schema.schemata', ['schema_name' => $schemaName]);
        if ($result = $this->query($sql)) {
            return boolify($result->fetchColumn(0));
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
    public function createSchema(?string $schemaName = null): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        if (!$schemaName) {
            $schemaName = $this->getSchemaName();
        }
        $sql = $queryBuilder->create($schemaName, 'schema', true);

        return false !== $this->exec($sql);
    }

    /**
     * @param array<string> $columns
     *
     * @return array<mixed>|false
     */
    protected function listInformationSchema(string $table, array $columns, mixed $criteria): array|false
    {
        $queryBuilder = $this->getQueryBuilder();
        $sql = $queryBuilder->select($columns)
            ->from('information_schema.'.$table)
            ->where($criteria)
            ->toString()
        ;

        $rows = [];
        $result = $this->query($sql);
        if ($result) {
            while ($row = $result->fetch()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
