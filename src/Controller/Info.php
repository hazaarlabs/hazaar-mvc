<?php

declare(strict_types=1);

namespace Hazaar\Controller;

class Info extends Action
{
    public function index(): void
    {
        $this->layout('@views/info');
        $this->view->populate([
            'version' => HAZAAR_VERSION,
            // @phpstan-ignore-next-line
            'time' => (microtime(true) - HAZAAR_START) * 1000,
        ]);
    }
}
