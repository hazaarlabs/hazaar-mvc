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

namespace Hazaar\Auth\Adapter;

use Hazaar\Application;
use Hazaar\Auth\Adapter;
use Hazaar\Cache;
use Hazaar\Map;
use Hazaar\Session as SessionCache;

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
 *
 * @implements  \ArrayAccess<string, mixed>
 */
abstract class Session extends Adapter implements \ArrayAccess
{
    protected Cache $session;

    /**
     * @param array<mixed>|Map $cacheConfig
     */
    public function __construct(null|array|Map $cacheConfig = null, ?Cache $cacheBackend = null)
    {
        parent::__construct([
            'cache' => [
                'backend' => 'session',
                'cookie' => 'hazaar-auth',
            ],
        ]);
        $cacheConfig = new Map([
            'use_pragma' => false,
            'lifetime' => $this->options['timeout'],
            'session_name' => $this->options['cache']['cookie'],
        ], $cacheConfig);
        if ($cacheBackend instanceof Cache) {
            $cacheBackend->configure($cacheConfig);
            $this->session = $cacheBackend;
        } elseif ('session' === $this->options['cache']['backend']) {
            $this->session = new SessionCache($cacheConfig);
        } else {
            $this->session = new Cache($this->options['cache']['backend'], $cacheConfig);
        }
        if ($this->session->has('hazaar_auth_identity', true)
            && $this->session->has('hazaar_auth_token', true)
            && hash($this->options['token']['hash'], $this->session['hazaar_auth_identity']) === $this->session['hazaar_auth_token']) {
            $this->identity = $this->session['hazaar_auth_identity'];
        }
        if ($this->session->has('hazaar_auth_identity') && $this->session->has('hazaar_auth_token')) {
            $id = $this->getIdentifier($this->session['hazaar_auth_identity']);
            $hash = $this->options['token']['hash'];
            if (hash($hash, $id) !== $this->session['hazaar_auth_token']) {
                $this->deauth();
            }
        }
        if ($this->options->has('data_fields')) {
            $this->setDataFields($this->options['data_fields']->toArray());
        }
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    public function &get(string $key): mixed
    {
        return $this->session->get($key);
    }

    public function &__get(string $key): mixed
    {
        return $this->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->session->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->session->has($key);
    }

    public function authenticate(?string $identity = null, ?string $credential = null, bool $autologin = false): bool
    {
        $auth = parent::authenticate($identity, $credential, $autologin);
        if (is_array($auth)) {
            if (array_key_exists('data', $auth)) {
                $this->session->setValues($auth['data']);
            }
            $this->session['hazaar_auth_identity'] = $identity;
            $this->session['hazaar_auth_token'] = hash($this->options['token']['hash'], $this->getIdentifier($identity));
            if ('cli' !== \php_sapi_name()
                && boolify($autologin)
                && $this->options['autologin']['period'] > 0) {
                /*
                 * $credential should be encrypted, as stored in the datasource (ie: database), so we
                 * md5 that to totally obscure it. If it is not encrypted then it is not being
                 * stored encrypted and the developer should re-think their auth strategy but
                 * we offer some minor protection from that stupidity here.
                 */
                $data = base64_encode(http_build_query([
                    'identity' => $identity,
                    'hash' => hash($this->options['autologin']['hash'], $this->getIdentifier($auth['credential'].$identity)),
                ]));
                $cookie = $this->getAutologinCookieName();
                $timeout = (86400 * $this->options['autologin']['period']);
                setcookie($cookie, $data, time() + $timeout, Application::path(), '', true, true);
            }
            $this->authenticationSuccess($identity, $this->extra);

            return true;
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    public function getUserData(): array
    {
        return $this->session->toArray();
    }

    public function authenticated(): bool
    {
        if ($this->session->has('hazaar_auth_identity')
            && $this->session->has('hazaar_auth_token')
            && hash($this->options->get('token.hash'), $this->getIdentifier($this->session['hazaar_auth_identity'])) === $this->session['hazaar_auth_token']) {
            $this->identity = $this->session['hazaar_auth_identity'];

            return true;
        }
        $headers = hazaar_request_headers();
        if ($authorization = ake($headers, 'Authorization')) {
            list($method, $code) = explode(' ', $authorization);
            if ('basic' === strtolower($method)) {
                list($identity, $credential) = explode(':', base64_decode($code));

                return $this->authenticate($identity, $credential);
            }
        } elseif ($this->canAutoLogin()) {
            // If we've got a cookie set, use the identity to look up credentials
            $cookie_name = $this->getAutologinCookieName();
            parse_str(base64_decode(ake($_COOKIE, $cookie_name, '')), $cookie);
            if ($cookie) {
                if ($identity = urldecode(ake($cookie, 'identity'))) {
                    $this->setIdentity($identity);
                }
                if ($auth = $this->queryAuth($identity, $this->extra)) {
                    $hash = hash($this->options['autologin']['hash'], $this->getIdentifier($auth['credential'].$identity));
                    // Check the cookie credentials against the ones we just got from the adapter
                    if ($identity === $auth['identity']
                        && $hash === ake($cookie, 'hash')) {
                        if (array_key_exists('data', $auth)) {
                            $this->session->setValues($auth['data']);
                        }
                        $this->session['hazaar_auth_identity'] = $identity;
                        $this->session['hazaar_auth_token'] = hash($this->options['token']['hash'], $this->getIdentifier($identity));
                        $this->authenticationSuccess($identity, $this->extra);

                        return true;
                    }
                    $this->deauth();
                }
            }
        }

        return false;
    }

    public function deauth(): bool
    {
        $this->session->clear();
        $cookie = $this->getAutologinCookieName();
        if (isset($_COOKIE[$cookie])) {
            unset($_COOKIE[$cookie]);
            setcookie($cookie, '', time() - 3600, Application::path(), '', true, true);
        }

        return true;
    }

    public function getToken(): string
    {
        return $this->session['hazaar_auth_token'];
    }

    public function getTokenType(): false|string
    {
        return 'Basic';
    }

    /**
     * Array Access Methods.
     *
     * These methods allows accessing user data as array attributes of the auth object. These methods do not allow this
     * data to be modified in any way.
     */
    public function offsetExists(mixed $key): bool
    {
        return $this->session->has($key);
    }

    public function &offsetGet(mixed $key): mixed
    {
        if ($this->session->has($key)) {
            return $this->session->get($key);
        }
        $result = null; // Required to return variables by reference

        return $result;
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->session->set($key, $value);
    }

    public function offsetUnset(mixed $key): void
    {
        $this->session->remove($key);
    }

    /**
     * Overload function called when a user is successfully authenticated.
     *
     * This can occur when calling authenticate() or authenticated() where a session has been saved.  This default method does nothing but can
     * be overridden.
     *
     * @param array<mixed> $data
     */
    protected function authenticationSuccess(string $identity, array $data): void {}
}
