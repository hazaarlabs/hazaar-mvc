<?php

declare(strict_types=1);

namespace Hazaar\DBI;

class Statement extends \PDOStatement
{
    public bool $aliased = false;

    protected function __construct() {}

    /**
     * @param null|array<string, mixed> $params
     */
    public function execute(?array $params = null): bool
    {
        if (null === $params || false === $this->aliased) {
            return parent::execute($params);
        }
        $statementParams = [];
        foreach ($params as $key => $value) {
            $statementParams["{$key}0"] = $value;
        }

        return parent::execute($statementParams);
    }
}
