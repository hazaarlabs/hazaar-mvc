<?php

namespace Hazaar\Middleware\Interface;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;

interface Middleware
{
    /**
     * Handles the request and returns a response.
     *
     * @param Request  $request The request object
     * @param callable $next    The next middleware or controller to call
     *
     * @return Response The response object
     */
    public function handle(Request $request, callable $next): Response;
}
