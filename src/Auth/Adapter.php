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

namespace Hazaar\Auth;

use Hazaar\Application;
use Hazaar\Application\Request;
use Hazaar\Application\Request\HTTP;
use Hazaar\Auth\Adapter\Exception\Unauthorised;
use Hazaar\Auth\Adapter\Exception\UnknownStorageAdapter;
use Hazaar\Map;

/**
 * Abstract authentication adapter.
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
 *
 * @implements \ArrayAccess<string,mixed>
 */
abstract class Adapter implements Interfaces\Adapter, \ArrayAccess
{
    protected Map $options;
    protected Interfaces\Storage $storage;
    protected ?string $identity = null;
    protected ?string $credential = null;

    /**
     * Extra data fields to store from the user record.
     *
     * @var array<string>
     */
    protected array $extra = [];

    private bool $noCredentialHashing = false;

    /**
     * Construct the adapter.
     *
     * @param array<mixed>|Map $config The configuration options
     */
    public function __construct(null|array|Map $config = [])
    {
        $defaults = [
            'storage' => 'session',
            'encryption' => [
                'hash' => 'sha1',
                'count' => 1,
                'salt' => 'hazaar-default-salt',
                'useIdentity' => false,
            ],
            'autologin' => [
                'cookie' => 'hazaar-auth-refresh',
                'period' => 1,
                'hash' => 'sha1',
            ],
            'timeout' => 3600,
        ];
        $this->options = Map::_($defaults, Application::getInstance()->config['auth'], $config);
        $storage = $this->options->get('storage', 'session');
        $this->setStorageAdapter($storage, $this->options->get($storage, []));
        if ($this->options->has('data_fields')) {
            $this->setDataFields($this->options['data_fields']->toArray());
        }
    }

    /**
     * Sets the identity for the authentication adapter.
     *
     * @param string $identity the identity to be set
     */
    public function setIdentity(string $identity): void
    {
        $this->identity = $identity;
    }

    /**
     * Sets the credential for authentication.
     *
     * @param string $credential the credential to be set
     */
    public function setCredential(string $credential): void
    {
        $this->credential = $credential;
    }

    /**
     * Retrieves the identity of the current user.
     *
     * @return null|string the identity of the user, or null if not set
     */
    public function getIdentity(): ?string
    {
        return $this->identity;
    }

    /**
     * Get the encrypted hash of a credential/password.
     *
     * This method uses the "encryption" options from the application configuration to generate
     * a password hash based on the supplied password.  If no password is supplied then the
     * currently set credential is used.
     *
     * NOTE: Keep in mind that if no credential is set, or it's null, or an empty string, this
     * will still return a valid hash of that empty value using the defined encryption hash chain.
     */
    public function getCredentialHash(?string $credential = null): ?string
    {
        if (null === $credential) {
            $credential = $this->credential;
        }
        if (!$credential || true === $this->noCredentialHashing) {
            return $credential;
        }
        $hash = '';
        if (true === $this->options['encryption']['useIdentity']) {
            $credential = $this->identity.':'.$credential;
        }
        $count = $this->options['encryption']['count'];
        $algos = $this->options['encryption']['hash'];
        if ($algos instanceof Map) {
            $algos = $algos->toArray();
        } elseif (!is_array($algos)) {
            $algos = [$algos];
        }
        $salt = $this->options['encryption']['salt'];
        $hash_algos = hash_algos();
        if (!is_string($salt)) {
            $salt = '';
        }
        for ($i = 1; $i <= $count; ++$i) {
            foreach ($algos as $algo) {
                if (!in_array($algo, $hash_algos)) {
                    continue;
                }
                $hash = hash($algo, $hash.$credential.$salt);
            }
        }

        return $hash;
    }

    /**
     * Authenticates a user based on the provided identity and credential.
     *
     * @param null|string $identity   The identity of the user (e.g., username or email).
     * @param null|string $credential The credential of the user (e.g., password).
     * @param bool        $autologin  whether to enable autologin
     *
     * @return mixed returns true if authentication is successful, false otherwise
     */
    public function authenticate(?string $identity = null, ?string $credential = null, bool $autologin = false): mixed
    {
        // Save the authentication data
        if ($identity) {
            $this->setIdentity($identity);
        }
        if ($credential) {
            $this->setCredential($credential);
        }
        if (!($identity = $this->getIdentity())) {
            return false;
        }
        $auth = $this->queryAuth($identity, $this->options->get('extra', [], true)->values());
        if (is_array($auth)
            && array_key_exists('identity', $auth)
            && array_key_exists('credential', $auth)
            && $auth['identity'] === $this->getIdentity()
            && hash_equals($this->getCredentialHash(), $auth['credential'])) {
            unset($auth['credential']);
            $this->storage->write($auth);
            $this->authenticationSuccess($identity, $auth);

            return true;
        }
        $this->authenticationFailure($identity, $auth);
        $this->clear();

        return false;
    }

    /**
     * Authenticates an HTTP request using Basic Authentication.
     *
     * This method checks if the request contains a valid 'Authorization' header
     * with Basic Authentication credentials. It decodes the credentials and
     * verifies them using the `authenticate` method.
     *
     * @param Request $request the HTTP request to authenticate
     *
     * @return bool returns true if the request is authenticated successfully, false otherwise
     *
     * @throws Unauthorised if the authentication fails
     */
    public function authenticateRequest(Request $request): bool
    {
        if (!$request instanceof HTTP) {
            return false;
        }
        $auth = $request->getHeader('Authorization');
        if (!$auth) {
            return false;
        }
        $auth = explode(' ', $auth);
        if (2 !== count($auth) || 'basic' !== strtolower($auth[0])) {
            return false;
        }
        $auth = base64_decode($auth[1]);
        if (!$auth) {
            return false;
        }
        $auth = explode(':', $auth);
        if (2 !== count($auth)) {
            return false;
        }
        if (false === $this->authenticate($auth[0], $auth[1])) {
            throw new Unauthorised();
        }

        return true;
    }

    /**
     * Checks if the user is authenticated.
     *
     * This method verifies if the storage is not empty and contains an 'identity' key.
     * If the storage is empty or does not have the 'identity' key, it clears the storage
     * and returns false. Otherwise, it returns true indicating the user is authenticated.
     *
     * @return bool true if the user is authenticated, false otherwise
     */
    public function authenticated(): bool
    {
        if (true === $this->storage->isEmpty()) {
            return false;
        }
        if (false === $this->storage->has('identity')) {
            $this->storage->clear();

            return false;
        }

        return true;
    }

    /**
     * Check that the supplied password is correct for the current identity.
     *
     * This is useful for checking an account password before allowing something important to be updated.
     * This does the same steps as authenticate() but doesn't actually do the authentication.
     */
    public function check(string $credential): bool
    {
        if ($identity = $this->getIdentity()) {
            $auth = $this->queryAuth($identity, $this->options->get('extra', [])->toArray());
            if (false === $auth || !(is_array($auth)
                && array_key_exists('identity', $auth)
                && array_key_exists('credential', $auth))) {
                return false;
            }
            if ($auth['identity'] === $identity
                && hash_equals($this->getCredentialHash($credential), $auth['credential'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clears the authentication storage.
     *
     * This method checks if the storage is empty. If it is not empty, it clears the storage
     * and returns true. If the storage is already empty, it returns false.
     *
     * @return bool returns true if the storage was cleared, false if the storage was already empty
     */
    public function clear(): bool
    {
        if ($this->storage->isEmpty()) {
            return false;
        }
        $this->storage->clear();

        return true;
    }

    /**
     * Helper method that sets the basic auth header and throws an unauthorised exception.
     */
    public function unauthorised(): void
    {
        throw new Unauthorised(true);
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
    public function disableCredentialHashing(bool $value = true): void
    {
        $this->noCredentialHashing = $value;
    }

    /**
     * Retrieves a value from the storage based on the provided key.
     *
     * @param string $key the key used to retrieve the value from the storage
     *
     * @return mixed the value associated with the provided key
     */
    public function get(string $key): mixed
    {
        return $this->storage->get($key);
    }

    /**
     * Sets a value in the storage with the specified key.
     *
     * @param string $key   the key under which the value will be stored
     * @param mixed  $value the value to be stored
     */
    public function set(string $key, mixed $value): void
    {
        $this->storage->set($key, $value);
    }

    /**
     * Checks if a given key exists in the storage.
     *
     * @param string $key the key to check for existence in the storage
     *
     * @return bool returns true if the key exists, false otherwise
     */
    public function has(string $key): bool
    {
        return $this->storage->has($key);
    }

    public function unset(string $key): void
    {
        $this->storage->unset($key);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->storage->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->storage->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->storage->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->storage->unset($offset);
    }

    /**
     * Retrieves authentication data from the storage.
     *
     * @return array<string,mixed> the authentication data
     */
    public function getAuthData(): array
    {
        return $this->storage->read();
    }

    /**
     * Returns the storage session token.
     *
     * @return array<string,string> Storage token will be an array with at least a 'token' key
     *                              and optionally a 'refresh' key
     */
    public function getToken(): ?array
    {
        return $this->storage->getToken();
    }

    /**
     * Sets the storage adapter for authentication.
     *
     * @param string                  $storage the name of the storage adapter to use
     * @param array<string,mixed>|Map $options optional configuration options for the storage adapter
     *
     * @return bool returns true if the storage adapter was successfully set
     *
     * @throws UnknownStorageAdapter if the specified storage adapter class does not exist
     */
    public function setStorageAdapter(string $storage, array|Map $options = []): bool
    {
        $class = '\Hazaar\Auth\Storage\\'.ucfirst($storage);
        if (!class_exists($class)) {
            throw new UnknownStorageAdapter($storage);
        }
        $this->storage = new $class(Map::_($options));
        if (!$this->storage->isEmpty()) {
            $this->identity = $this->storage->get('identity');
        }

        return true;
    }

    /**
     * Generates a hashed identifier for the given identity string.
     *
     * This method takes an identity string and returns its SHA-1 hash. If the
     * identity string is empty or null, the method returns null.
     *
     * @param string $identity the identity string to be hashed
     *
     * @return null|string the SHA-1 hash of the identity string, or null if the identity is empty
     */
    protected function getIdentifier(string $identity): ?string
    {
        if (!$identity) {
            return null;
        }

        return hash('sha1', $identity);
    }

    /**
     * Set the extra data fields.
     *
     * @param array<string> $fields The extra data fields
     */
    protected function setDataFields(array $fields): void
    {
        $this->options['extra'] = $fields;
    }

    /**
     * Overload function called when a user is successfully authenticated.
     *
     * This can occur when calling authenticate() or authenticated() where a
     * session has been saved.  This default method does nothing but can be
     * overridden.
     *
     * @param string       $identity The identity that was successfully authenticated
     * @param array<mixed> $data     The data returned from the authentication query
     */
    protected function authenticationSuccess(string $identity, array $data): void {}

    /**
     * Overload function called when a user fails authentication.
     *
     * This can occur when calling authenticate() or authenticated() where a
     * session has been saved.  This default method does nothing but can be
     * overridden.
     *
     * @param string       $identity the identity that failed authentication
     * @param array<mixed> $data     the data returned from the authentication query
     */
    protected function authenticationFailure(string $identity, array $data): void {}
}
