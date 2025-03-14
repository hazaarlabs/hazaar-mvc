<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD\Traits\PDO;

use Hazaar\DBI\Interface\QueryBuilder;

trait Statement
{
    public function prepare(QueryBuilder $queryBuilder): \PDOStatement
    {
        $statement = $this->pdo->prepare($queryBuilder->toString(prepareValues: true));
        $values = $queryBuilder->getCriteriaValues();
        foreach ($values as $key => $value) {
            $statement->bindValue($key, $value);
        }

        return $statement;
    }
}
