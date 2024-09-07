<?php

declare(strict_types=1);

namespace Hazaar\Controller;

/**
 * Class Info.
 *
 * This class extends the Action class and is responsible for displaying the information page.
 */
class Info extends Action
{
    /**
     * Displays the information page.
     *
     * This method sets the layout to '@views/info' and populates the view with
     * the current version of the application and the time taken since the start
     * of the application in milliseconds.
     */
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
