<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action\Component;

use Hazaar\Model;

abstract class BaseComponent extends Model
{
    public function changed(self $component): ?self
    {
        return null;
    }
}
