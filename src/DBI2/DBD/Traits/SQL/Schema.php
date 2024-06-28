<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait Schema
{
    /**
     * @param array<string> $columns
     *
     * @return array<mixed>|false
     */
    private function listInformationSchema(string $table, array $columns, mixed $criteria): array|false
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
