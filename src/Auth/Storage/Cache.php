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

use Hazaar\Application;
use Hazaar\Application\Request;
use Hazaar\Auth\Adapter;
use Hazaar\Auth\Interfaces\Storage;
use Hazaar\Cache as HazaarCache;

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
class Cache implements Storage
{
    protected ?HazaarCache $session = null;

    /**
     * @var array<string,mixed>
     */
    private array $data = [];

    /**
     * @var array<string,mixed>
     */
    private array $config;
    private ?string $sessionID = null;

    /**
     * @var array<string>
     */
    private static array $blackListedBackends = [
        'apc',
    ];

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = array_enhance($config, [
            'backend' => 'file',
            'usePragma' => false,
            'lifetime' => 3600, // 1 hour
            'name' => 'hazaar-auth',
        ]);
        if (array_key_exists($this->config['name'], $_COOKIE)) {
            $this->initSession($_COOKIE[$this->config['name']]);
            $this->data = $this->session->get('data', []);
        }
    }

    public function close(): void
    {
        if ($this->session) {
            $this->session->set('data', $this->data);
        }
    }

    public function isEmpty(): bool
    {
        if (null === $this->session
            || false === isset($this->session)
            || 0 === $this->session->count()
            || ($_SERVER['HTTP_USER_AGENT'] ?? null) !== $this->get('user-agent')
            || Request::getRemoteAddr() !== $this->get('ip-address')) {
            return true;
        }

        return false;
    }

    public function read(): array
    {
        if (!$this->session) {
            return $this->data;
        }

        return array_merge($this->session->toArray(), ['data' => $this->data]);
    }

    public function write(array $data): void
    {
        if (!$this->session) {
            $this->initSession();
        }
        if (!isset($data['data']) || !is_array($data['data'])) {
            $data['data'] = [];
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $data['data']['user-agent'] = $_SERVER['HTTP_USER_AGENT'];
            $data['data']['ip-address'] = Request::getRemoteAddr();
        }

        $this->session->populate($data);
        $this->data = $data['data'];
    }

    public function has(string $key): bool
    {
        if ('identity' === $key) {
            if (!$this->session) {
                throw new \Exception('Session not initialized');
            }

            return isset($this->session['identity']);
        }

        return isset($this->data[$key]);
    }

    public function get(string $key): mixed
    {
        if ('identity' === $key) {
            if (!$this->session) {
                return null;
            }

            return $this->session['identity'] ?? null;
        }

        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        if ('identity' !== $key) {
            $this->data[$key] = $value;
        }
    }

    public function unset(string $key): void
    {
        if ('identity' !== $key) {
            unset($this->data[$key]);
        }
    }

    public function clear(): void
    {
        if ($this->session) {
            $this->session->clear();
            $this->data = [];
        }
        setcookie($this->config['name'], '', time() - 3600, '/', '', true, true);
    }

    public function getToken(): ?array
    {
        return ['token' => $this->sessionID];
    }

    private function initSession(?string $sessionID = null): void
    {
        if (null !== $this->session) {
            throw new \Exception('Session already initialized');
        }
        if (null === $sessionID) {
            $sessionID = hash('sha256', uniqid('hazaar-auth', true));
        }
        if (!($sessionID && 64 === strlen($sessionID))) {
            throw new \Exception('Invalid session ID');
        }
        if (in_array($this->config['backend'], self::$blackListedBackends, true)) {
            throw new \Exception("Cache backend '{$this->config['backend']}' not supported for session storage");
        }
        $this->sessionID = $sessionID;
        $this->session = new HazaarCache($this->config['backend'], $this->config, $sessionID);
        Application::getInstance()->registerOutputFunction(function () use ($sessionID): void {
            setcookie($this->config['name'], $sessionID, 0, '/', '', true, true);
        });
    }
}
