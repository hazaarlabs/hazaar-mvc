<?php

namespace Hazaar\Auth\Storage\Middleware;

use Hazaar\Application\Request;
use Hazaar\Auth\Storage\Jwt as JwtStorage;
use Hazaar\Controller\Response;
use Hazaar\Middleware\Interface\Middleware;

class Jwt implements Middleware
{
    public bool $clearCookie = false;
    public bool $writeCookie = false;
    public int $timeout = 3600;
    public int $refresh = 604800; // Default refresh period of 7 days
    private JwtStorage $jwt;

    public function __construct(JwtStorage $jwtStorage)
    {
        $this->jwt = $jwtStorage;
    }

    public function handle(Request $request, callable $next, mixed ...$args): Response
    {
        if (true === $this->clearCookie) {
            setcookie('hazaar-auth-token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
            setcookie('hazaar-auth-refresh', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
        } elseif (true === $this->writeCookie) {
            $tokens = $this->jwt->getToken();
            setcookie('hazaar-auth-token', $tokens['token'], time() + $this->timeout, '/', $_SERVER['HTTP_HOST'], true, true);
            if (isset($tokens['refresh'])) {
                setcookie('hazaar-auth-refresh', $tokens['refresh'], time() + $this->refresh, '/', $_SERVER['HTTP_HOST'], true, true);
            }
        }

        return $next($request);
    }
}
