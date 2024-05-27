<?php

namespace Application\Controllers;

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

    /**
     * @route('/foo/<string:word>', methods=['GET'])
     */
    public function rest(?string $word = null): mixed
    {
        return 'bar: '.($word ?? '');
    }

    protected function init(Request $request): void
    {
        $this->cacheAction('index', 60);
    }
}
