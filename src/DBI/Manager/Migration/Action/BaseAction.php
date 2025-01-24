<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Manager\Migration\Interface\Spec;
use Hazaar\Model;

abstract class BaseAction extends Model implements Spec {}
