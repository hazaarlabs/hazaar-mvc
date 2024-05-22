<?php

declare(strict_types=1);

namespace Hazaar\View\Helper;

use Hazaar\View;
use Hazaar\View\Helper;

class Authentication extends Helper
{
    public function init(array $args = []): bool
    {
        return true;
    }

    public function run(View $view): bool
    {
        return true;
    }
}
