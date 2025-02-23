<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action\Component;

use Hazaar\Model;

class ConstraintReference extends Model
{
    public string $table;
    public string $column;
}
