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

namespace Hazaar\Auth;

/**
 * Abstract authentication adapter
 *
 * This class is the base class for all of the supplied authentication adapters.  This class takes care
 * of the password hash generation and session management, including the autologin function.
 *
 * Available options are:
 *
 * # encryption.hash - default: sha256
 * The hash algorithm to use to encrypt passwords.  This can be a a single algorith, such
 * as sha256, sha1 or any other algorithm supported by the PHP hash() function.  You can use the hash_algos()
 * function to get a list of available algorithms.  Any unsupported algorithms are silently ignored.
 *
 * This option can also be an array of algorithms.  In which case each one will be applied in the order
 * specified.  During each iteration the hash will be appended with the original password (this helps prevent
 * hash collisions) along with any salt value (see below) before being hashed with the next algorithm.
 *
 * The default is sha256 for security.  Please note that this breaks backwards compatibility with the 1.0
 * version of this module.
 *
 * # Configuration Directives
 *
 * ## encryption.count - default: 1
 * For extra obfuscation, it's possible to "hash the hash" this many times.  This is the old method we used
 * to add extra security to the hash, except we now also append the original password to the hash before
 * hashing it. (too much hash?).  In the case where the encryption.hash is a list of algorithms, each one
 * of these will be applied as above for each count.  So for example, if you have a list of 3 algorithms
 * and the count is 3, your password will be hashed 9 times.
 *
 * ## encryption.salt - default: null
 * For more security a salt value can be set which will be appended to each password when being hashed.  If
 * the password is being hashed multiple times then the salt is appended to the hash + password.
 *
 * This is now more often used as a cache timeout value because on logon, certain data is obtained for a user
 * and stored in cache.  Sometimes obtaining this data can be processor intensive so we don't want to do it
 * on every page load.  Instead we do it, cache it, and then only do it again once this time passes.
 *
 * # Example Config (application.json)
 *
 * ```
 * {
 *     "development": {
 *         "encryption": {
 *             "hash": [ "md5", "sha1", "sha256" ],
 *             "salt": "mysupersecretsalt"
 *         },
 *         "autologin": {
 *             "period": 365,
 *             "hash": "sha1"
 *         },
 *         "timeout": 28800
 *     }
 * }
 * ```
 */
abstract class Adapter implements Adapter\_Interface
{
    protected $options;
    protected $identity;
    protected $credential;

    // Extra data fields to store from the user record
    protected $extra = [];
    private $no_credential_hashing = false;

    public function __construct($config = [])
    {
        $this->options = new \Hazaar\Map([
            'encryption' => [
                'hash' => 'sha1',
                'count' => 1,
                'salt' => '',
                'use_identity' => false
            ],
            'autologin' => [
                'cookie' => 'hazaar-auth-autologin',
                'period' => 1,
                'hash'  => 'sha1'
            ],
            'token' => [
                'hash' => 'sha1'
            ],
            'timeout' => 3600
        ], \Hazaar\Application::getInstance()->config['auth']);
        $this->options->extend($config);
        if($this->options->has('data_fields')) {
            $this->setDataFields($this->options->data_fields->toArray());
        }
    }

    public function setIdentity($identity)
    {
        $this->identity = $identity;
    }

    public function setCredential($credential)
    {
        $this->credential = $credential;
    }

    public function getIdentity()
    {
        return $this->identity;
    }

    /**
     * Get the encrypted hash of a credential/password
     *
     * This method uses the "encryption" options from the application configuration to generate
     * a password hash based on the supplied password.  If no password is supplied then the
     * currently set credential is used.
     *
     * NOTE: Keep in mind that if no credential is set, or it's null, or an empty string, this
     * will still return a valid hash of that empty value using the defined encryption hash chain.
     *
     * @param mixed $credential
     * @return \null|string
     */
    public function getCredential($credential = null)
    {
        if($credential === null) {
            $credential = $this->credential;
        }
        if(!$credential || $this->no_credential_hashing === true) {
            return $credential;
        }
        $hash = false;
        if($this->options->encryption['use_identity'] === true) {
            $credential =  $this->identity . ':' . $credential;
        }
        $count = $this->options->encryption['count'];
        $algos = $this->options->encryption['hash'];
        if(!\Hazaar\Map::is_array($algos)) {
            $algos = [$algos];
        }
        $salt = $this->options->encryption['salt'];
        $hash_algos = hash_algos();
        if(!is_string($salt)) {
            $salt = '';
        }
        for($i = 1; $i <= $count; $i++) {
            foreach($algos as $algo) {
                if(!in_array($algo, $hash_algos)) {
                    continue;
                }
                $hash = hash($algo, $hash . $credential . $salt);
            }
        }
        return $hash;
    }

    protected function getIdentifier($identity)
    {
        if(!$identity) {
            return false;
        }
        return hash('sha1', $identity);
    }

    protected function setDataFields(array $fields)
    {
        $this->extra = $fields;
    }

    public function authenticate($identity = null, $credential = null, $autologin = false, &$data = null)
    {
        /*
         * Save the authentication data
         */
        if($identity) {
            $this->setIdentity($identity);
        }
        if($credential) {
            $this->setCredential($credential);
        }
        $auth = $this->queryAuth($this->getIdentity(), $this->extra);
        if($auth === false || !(is_array($auth)
            && array_key_exists('identity', $auth)
            && array_key_exists('credential', $auth))) {
            $this->deauth();
            return false;
        }
        if($auth['identity'] === $this->getIdentity()
            && $auth['credential'] === $this->getCredential()) {
            if(array_key_exists('data', $auth)) {
                $data = $auth['data'];
            }
            return $auth;
        }
        return false;
    }

    public function authenticated()
    {
        return false;
    }

    /**
     * Check that the supplied password is correct for the current identity.
     *
     * This is useful for checking an account password before allowing something important to be updated.
     * This does the same steps as authenticate() but doesn't actually do the authentication.
     *
     * @param mixed $credential
     * @return boolean
     */
    public function check($credential)
    {
        $auth = $this->queryAuth($this->getIdentity(), $this->extra);
        if($auth === false || !(is_array($auth)
            && array_key_exists('identity', $auth)
            && array_key_exists('credential', $auth))) {
            return false;
        }
        if($auth['identity'] === $this->getIdentity()
            && $auth['credential'] === $this->getCredential($credential)) {
            return true;
        }
        return false;
    }

    public function deauth()
    {
        return true;
    }

    /**
     * Helper method that sets the basic auth header and throws an unauthorised exception
     *
     * @throws \Exception
     */
    public function unauthorised()
    {
        header('WWW-Authenticate: Basic');
        throw new \Exception('Unauthorised!', 401);
    }

    protected function canAutoLogin()
    {
        $cookie = $this->getAutologinCookieName();
        return ($this->options['autologin']['period'] > 0 && isset($_COOKIE[$cookie]));
    }

    protected function getAutologinCookieName()
    {
        return $this->options['autologin']['cookie'];
    }

    public function getTokenType()
    {
        return 'Basic';
    }

    /**
     * Toggles on/off the internal credential hashing algorithm.
     *
     * This is useful is you want to authenticate with an already hashed credential.
     *
     * WARNING:  This should NOT normally be used.  And if it IS used, it should only be used to authenticate credentials
     * supplied internally by the application itself, and not provided by a user/client/etc.  Disabling password hash
     * essentially turns this all into clear text credentials.
     */
    public function disableCredentialHashing($value = true)
    {
        $this->no_credential_hashing = boolify($value);
    }
}
