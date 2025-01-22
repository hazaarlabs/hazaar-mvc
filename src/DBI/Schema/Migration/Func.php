<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Migration;

use Hazaar\Model;

class Func extends Model
{
    public string $name;
    public string $content;
}
