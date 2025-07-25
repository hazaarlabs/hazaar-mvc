<?php

namespace App\Middleware;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\Controller\Response\HTTP\Forbidden;
use Hazaar\Middleware\Interface\Middleware;

class Auth implements Middleware
{
    public function handle(Request $request, callable $next, mixed ...$args): Response
    {
        $authenticated = $request->get('authenticated', false);
        if (false === $authenticated) {
            return new Forbidden();
        }

        return $next($request);
    }
}
