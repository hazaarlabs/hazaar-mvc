<?php

/**
 * @file        Auth/Adapter.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

/**
 * User authentication namespace
 */

namespace Hazaar\Auth\Adapter;

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
 */
abstract class Session extends \Hazaar\Auth\Adapter implements \ArrayAccess
{
    protected $session;
    protected $options;
    protected $identity;
    protected $credential;
    // Extra data fields to store from the user record
    protected $extra = [];

    public function __construct($cache_config = [], $cache_backend = null)
    {
        parent::__construct([
            'cache' => [
                'backend' => 'session',
                'cookie' => 'hazaar-auth'
            ]
        ]);
        $cache_config = new \Hazaar\Map([
            'use_pragma' => false,
            'lifetime' => $this->options->timeout,
            'session_name' => $this->options->cache['cookie']
        ], $cache_config);
        if($cache_backend instanceof \Hazaar\Cache) {
            $cache_backend->configure($cache_config);
            $this->session = $cache_backend;
        } elseif($this->options->cache['backend'] === 'session') {
            $this->session = new \Hazaar\Session($cache_config);
        } else {
            $this->session = new \Hazaar\Cache($this->options->cache['backend'], $cache_config);
        }
        if($this->session->has('hazaar_auth_identity', true)
            && $this->session->has('hazaar_auth_token', true)
            && hash($this->options->token['hash'], $this->session->hazaar_auth_identity) === $this->session->hazaar_auth_token) {
            $this->identity = $this->session->hazaar_auth_identity;
        }
        if($this->session->has('hazaar_auth_identity') && $this->session->has('hazaar_auth_token')) {
            $id = $this->getIdentifier($this->session->hazaar_auth_identity);
            $hash = $this->options->token['hash'];
            if(hash($hash, $id) !== $this->session->hazaar_auth_token) {
                $this->deauth();
            }
        }
        if($this->options->has('data_fields')) {
            $this->setDataFields($this->options->data_fields->toArray());
        }
    }

    public function &get($key)
    {
        return $this->session->get($key);
    }

    public function &__get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        return $this->set($key, $value);
    }

    public function __isset($key)
    {
        return $this->has($key);
    }

    public function set($key, $value)
    {
        $this->session->set($key, $value);
    }

    public function has($key)
    {
        return $this->session->has($key);
    }

    public function authenticate($identity = null, $credential = null, $autologin = false, &$data = null)
    {
        $auth = parent::authenticate($identity, $credential, $autologin, $data);
        if(is_array($auth)) {
            if(array_key_exists('data', $auth)) {
                $this->session->setValues($auth['data']);
            }
            $this->session->hazaar_auth_identity = $identity;
            $this->session->hazaar_auth_token = hash($this->options->token['hash'], $this->getIdentifier($identity));
            if(\php_sapi_name() !== 'cli' &&
                boolify($autologin)
                && $this->options->autologin['period'] > 0) {
                /*
                 * $credential should be encrypted, as stored in the datasource (ie: database), so we
                 * md5 that to totally obscure it. If it is not encrypted then it is not being
                 * stored encrypted and the developer should re-think their auth strategy but
                 * we offer some minor protection from that stupidity here.
                 */
                $hash = array_key_exists('token', $auth)
                    ? $auth['token']
                    : hash($this->options->autologin['hash'], $this->getIdentifier($auth['credential'] . $identity));
                $cookie_data = base64_encode(http_build_query([
                    'identity' => $identity,
                    'hash' => $hash
                ]));
                $cookie = $this->getAutologinCookieName();
                $timeout = (86400 * $this->options->autologin['period']);
                setcookie($cookie, $cookie_data, time() + $timeout, \Hazaar\Application::path(), null, true, true);
            }
            return true;
        }
        return false;
    }

    public function getUserData()
    {
        return $this->session->toArray();
    }

    public function authenticated()
    {
        if($this->session->has('hazaar_auth_identity')
            && $this->session->has('hazaar_auth_token')
            && hash($this->options->get('token.hash'), $this->getIdentifier($this->session->hazaar_auth_identity)) === $this->session->hazaar_auth_token) {
            $this->identity = $this->session->hazaar_auth_identity;
            return true;
        }
        $headers = hazaar_request_headers();
        if($authorization = ake($headers, 'Authorization')) {
            list($method, $code) = explode(' ', $authorization);
            if(strtolower($method) === 'basic') {
                list($identity, $credential) = explode(':', base64_decode($code));
                return $this->authenticate($identity, $credential);
            }
        } elseif($this->canAutoLogin()) {
            /*
             * If we've got a cookie set, use the identity to look up credentials
             */
            $cookie_name = $this->getAutologinCookieName();
            parse_str(base64_decode(ake($_COOKIE, $cookie_name, '')), $cookie);
            if($cookie) {
                if($identity = urldecode(ake($cookie, 'identity'))) {
                    $this->setIdentity($identity);
                }
                if($auth = $this->queryAuth($identity, $this->extra)) {
                    $hash = array_key_exists('token', $auth) 
                        ? $auth['token']
                        : hash($this->options->autologin['hash'], $this->getIdentifier($auth['credential'] . $identity));
                    /*
                    * Check the cookie credentials against the ones we just got from the adapter
                    */
                    if($identity === $auth['identity']
                        && $hash === ake($cookie, 'hash')) {
                        if(array_key_exists('data', $auth)) {
                            $this->session->setValues($auth['data']);
                        }
                        $this->session->hazaar_auth_identity = $identity;
                        $this->session->hazaar_auth_token = hash($this->options->token['hash'], $this->getIdentifier($identity));
                        return true;
                    } else {
                        $this->deauth();
                    }
                }
            }
        }
        return false;
    }

    public function deauth()
    {
        $this->session->clear(true);
        $cookie = $this->getAutologinCookieName();
        if(isset($_COOKIE[$cookie])) {
            unset($_COOKIE[$cookie]);
            setcookie($cookie, '', time() - 3600, \Hazaar\Application::path(), null, true, true);
        }
        return true;
    }

    /**
     * Array Access Methods
     *
     * These methods allows accessing user data as array attributes of the auth object. These methods do not allow this
     * data to be modified in any way.
     */
    public function offsetExists($key): bool
    {
        return $this->session->has($key);
    }

    #[\ReturnTypeWillChange]
    public function &offsetGet($key)
    {
        if($this->session->has($key)) {
            return $this->session->get($key);
        }
        $result = null; // Required to return variables by reference
        return $result;
    }

    public function offsetSet($key, $value): void
    {
        $this->session->set($key, $value);
    }

    public function offsetUnset($key): void
    {
        $this->session->remove($key);
    }
}
