<?php

namespace App\Controller;

use Hazaar\Application\Request;
use Hazaar\Controller\Basic;

/**
 * @internal
 */
class Test extends Basic
{
    public function __default(): mixed
    {
        return 'Default action';
    }

    public function index(): mixed
    {
        return 'Uniqid: '.uniqid();
    }

    public function bar(?string $word = null): mixed
    {
        return 'bar: '.($word ?? '');
    }

    protected function init(): void
    {
        $this->cacheAction('index', 60);
    }
}
