<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Sync;

use Hazaar\DBI\Manager\Sync\Enums\RowStatus;
use Hazaar\Model;

class Stats extends Model
{
    public int $rows = 0;
    public int $inserts = 0;
    public int $updates = 0;
    public int $deletes = 0;
    public int $unchanged = 0;

    public function addRow(RowStatus $status): RowStatus
    {
        ++$this->rows;

        switch ($status) {
            case RowStatus::NEW:
                $this->inserts++;

                break;

            case RowStatus::UPDATED:
                $this->updates++;

                break;

            case RowStatus::UNCHANGED:
                $this->unchanged++;

                break;
        }

        return $status;
    }
}
