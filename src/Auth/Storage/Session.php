<?php

declare(strict_types=1);

/**
 * @file        Auth/Adapter.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
/**
 * User authentication namespace.
 */

namespace Hazaar\Auth\Storage;

use Hazaar\Application\Request\HTTP;
use Hazaar\Auth\Adapter;
use Hazaar\Auth\Interfaces\Storage;
use Hazaar\Auth\Storage\Exception\SessionStartFailed;
use Hazaar\Cache;
use Hazaar\Map;

/**
 * @brief       Session based authentication adapter
 *
 * @detail      This authentication adapter uses the session cache to store the user identity and token.
 *
 * # Configuration Directives
 *
 * ## autologin.period - default: 1
 * This is the period in which the autologin cookie will remain active (ie: will expire after this many
 * days).  The default is one day.
 *
 * # autologin.hash - default: md5
 * This is the hash algorithm used to encrypt the token placed in the cookie in the user's browser
 * session.  This data is hashed to ensure that it can not be manipulated by the user.
 *
 * ## token.hash - default: md5
 * The token hash is the value stored in the session cache and is used to confirm that a user
 * account is authenticated.  As an added security measure we apply a hash to this value so that plain
 * test passwords will never be stored in the session cache, even if there is no password encryption chain.
 *
 * ## timeout - default: 3600
 * For a standard login, this is the session expirey timeout.  Basically this is the maximum time in which
 * a session will ever be active.  If autologin is being used, then it is quite common to set this to a low
 * value to allow the user to be re-authenticated with the autologin token periodically.
 */
class Session implements Storage
{
    private string $sessionKey = 'hazaar_auth_storage';

    /**
     * @var array<string,mixed>
     */
    private array $session;

    public function __construct(Map $config)
    {
        if ($config->has('name')) {
            session_name($config['name']);
        }
        if (!(session_id() || (headers_sent() && PHP_SAPI === 'cli'))) {
            ini_set('session.cookie_secure', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            if ($config->has('timeout')) {
                $timeout = $config['timeout'];
                ini_set('session.gc_maxlifetime', $timeout * 2);
                ini_set('session.cookie_maxlifetime', $timeout * 2);
            }
            if (false === session_start()) {
                throw new SessionStartFailed();
            }
        }
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }
        $this->session = &$_SESSION;
    }

    public function isEmpty(): bool
    {
        if (false === isset($this->session[$this->sessionKey])
            || 0 === count($this->session[$this->sessionKey])
            || ($_SERVER['HTTP_USER_AGENT'] ?? null) !== $this->get('user-agent')
            || HTTP::getRemoteAddr() !== $this->get('ip-address')) {
            return true;
        }

        return false;
    }

    public function read(): array
    {
        return $this->session[$this->sessionKey] ?? [];
    }

    public function write(array $data): void
    {
        $this->session[$this->sessionKey] = $data;
        if (!isset($this->session[$this->sessionKey]['data']) || !is_array($this->session[$this->sessionKey]['data'])) {
            $this->session[$this->sessionKey]['data'] = [];
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $this->session[$this->sessionKey]['data']['user-agent'] = $_SERVER['HTTP_USER_AGENT'];
            $this->session[$this->sessionKey]['data']['ip-address'] = HTTP::getRemoteAddr();
        }
    }

    public function has(string $key): bool
    {
        return isset($this->session[$this->sessionKey]['identity']);
    }

    public function get(string $key): mixed
    {
        if ('identity' === $key) {
            return $this->session[$this->sessionKey]['identity'];
        }

        return $this->session[$this->sessionKey]['data'][$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->session[$this->sessionKey]['data'][$key] = $value;
    }

    public function unset(string $key): void
    {
        unset($this->session[$this->sessionKey]['data'][$key]);
    }

    public function clear(): void
    {
        unset($this->session[$this->sessionKey]);
        session_unset();
        session_destroy();
    }

    public function getToken(): ?array
    {
        return ['token' => session_id()];
    }
}
