<?php

namespace Hazaar\DBI\DBD\Traits\PDO;

trait Transaction
{
    public function begin(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function cancel(): bool
    {
        return $this->pdo->rollBack();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }
}
