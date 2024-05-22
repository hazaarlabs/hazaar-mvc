<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Struct;

use Hazaar\Model;

class Application extends Model
{
    protected string $path = APPLICATION_PATH;
    protected string $env = APPLICATION_ENV;
}
