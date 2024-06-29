<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait View
{
    /**
     * Retrieves a list of views from the database.
     *
     * @return array<int, array<string>>|false an array of views, or null if no views are found
     */
    public function listViews(): array|false
    {
        $sql = 'SELECT table_schema as "schema", table_name as name FROM INFORMATION_SCHEMA.views WHERE ';
        if ('public' != $this->queryBuilder->getSchemaName()) {
            $sql .= "table_schema = '{$this->queryBuilder->getSchemaName()}'";
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
        list($schema, $name) = $this->queryBuilder->parseSchemaName($name);
        $sql = 'SELECT table_name as name, trim(view_definition) as content FROM INFORMATION_SCHEMA.views WHERE table_schema='
            .$this->queryBuilder->prepareValue($schema).' AND table_name='.$this->queryBuilder->prepareValue($name);
        if ($result = $this->query($sql)) {
            return $result->fetch(\PDO::FETCH_ASSOC);
        }

        return false;
    }

    public function createView(string $name, mixed $content): bool
    {
        $sql = 'CREATE OR REPLACE VIEW '.$this->queryBuilder->schemaName($name).' AS '.rtrim($content, ' ;');

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
        $sql .= $this->queryBuilder->schemaName($name);
        if (true === $cascade) {
            $sql .= ' CASCADE';
        }

        return false !== $this->exec($sql);
    }
}
