<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait View
{
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
