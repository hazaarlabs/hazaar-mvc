<?php

declare(strict_types=1);

namespace Application\Controllers;

use Hazaar\Controller\Action;

class Index extends Action
{
    public function index(): void
    {
        /*
         * By default the Action controller (which we have extended here) uses a
         * Hazaar\View\Layout, which loads a layout view (application.phtml by default)
         * so here we add a 'sub-view' to the layout which displays out blue example box.
         */
        $this->view('index');
    }
}
