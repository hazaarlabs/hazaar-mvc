<?php

declare(strict_types=1);

namespace Hazaar\DBI;

class Statement extends \PDOStatement
{
    protected function __construct() {}

    /**
     * @param null|array<string, mixed> $params
     */
    public function execute(?array $params = null): bool
    {
        $statementParams = [];
        foreach ($params as $key => $value) {
            $statementParams["{$key}0"] = $value;
        }

        return parent::execute($statementParams);
    }
}
