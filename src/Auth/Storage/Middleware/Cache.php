<?php

namespace Hazaar\Auth\Storage\Middleware;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\Middleware\Interface\Middleware;

class Cache implements Middleware
{
    private string $name;
    private string $sessionID;

    public function __construct(string $name, string $sessionID)
    {
        $this->name = $name;
        $this->sessionID = $sessionID;
    }

    public function handle(Request $request, callable $next): Response
    {
        setcookie($this->name, $this->sessionID, 0, '/', '', true, true);

        return $next($request);
    }
}
