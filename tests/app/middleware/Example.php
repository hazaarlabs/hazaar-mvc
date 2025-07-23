<?php

namespace App\Middleware;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\Middleware\Interface\Middleware;

class Example implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        // Example middleware logic
        $response->setHeader('X-Example-Middleware', 'Active');

        return $response;
    }
}
