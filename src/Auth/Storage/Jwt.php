<?php

declare(strict_types=1);

namespace Hazaar\Auth\Storage;

use Hazaar\Application;
use Hazaar\Application\Request\HTTP;
use Hazaar\Auth\Adapter;
use Hazaar\Auth\Interfaces\Storage;
use Hazaar\Loader;
use Hazaar\Map;

/**
 * JWT Authentication Adapter.
 *
 * This class provides a JWT authentication adapter for the Hazaar MVC framework.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class JWT implements Storage, \ArrayAccess
{
    protected ?string $passphrase = null;
    protected string $privateKey;

    /**
     * @var array<mixed>
     */
    protected ?array $token = null;

    private Map $config;

    /**
     * @var array<string,mixed>
     */
    private ?array $data = null;
    private bool $writeCookie = false;

    public function __construct(?Map $config = null)
    {
        if (!($app = Application::getInstance())) {
            throw new \Exception('JWT Auth Storage requires an Application instance');
        }
        $app->registerOutputFunction([$this, 'writeToken']);
        $this->config = $config;
        $this->config->enhance([
            'alg' => 'HS256',
            'issuer' => 'hazaar-auth',
            'timeout' => 3600,
            'refresh' => 86400,
            'fingerprintIP' => true,
        ]);
        if ($this->config->has('passphrase')) {
            $this->passphrase = $this->config->get('passphrase');
        }
        if ($this->config->has('privateKey')) {
            $this->privateKey = $this->config->get('privateKey');
        } elseif ($this->config->has('privateKeyFile')) {
            if ($privateKeyFile = Loader::getFilePath(FILE_PATH_CONFIG, $this->config->get('privateKeyFile'))) {
                $this->privateKey = file_get_contents($privateKeyFile);
            }
        }
        if (!isset($this->privateKey)) {
            throw new \Exception('No private key set for JWT signing');
        }
        $this->checkToken();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function unset(string $key): void
    {
        unset($this->data[$key]);
    }

    public function isEmpty(): bool
    {
        return null === $this->data || !isset($this->data['identity']);
    }

    public function read(): array
    {
        return $this->data ?? [];
    }

    public function clear(): void
    {
        if (false === $this->isEmpty()) {
            setcookie('hazaar-auth-token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
            setcookie('hazaar-auth-refresh', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
        }
    }

    /**
     * Authorises the JWT token.
     *
     * @param array<string,mixed> $data
     */
    public function write(array $data): void
    {
        $this->data = $data;
        $this->writeCookie = true;
    }

    /**
     * ArrayAccess Functions.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function writeToken(): void
    {
        if (true !== $this->writeCookie) {
            return;
        }
        $out = [];
        $JWTBody = array_merge([
            'iss' => $this->config->get('issuer'),
            'iat' => time(),
            'exp' => time() + $this->config->get('timeout'),
            'sub' => $this->data['identity'],
        ], ake($this->data, 'data', []));
        $token = $this->buildToken($JWTBody);
        setcookie('hazaar-auth-token', $token, time() + $this->config->get('timeout'), '/', $_SERVER['HTTP_HOST'], true, true);
        if ($this->config->get('refresh')) {
            $refreshToken = $this->buildToken([
                'iss' => $JWTBody['iss'],
                'iat' => $JWTBody['iat'],
                'exp' => $JWTBody['iat'] + $this->config->get('refresh'),
                'sub' => $this->data['identity'],
            ], $this->buildRefreshTokenKey($this->data['credential']));
            setcookie('hazaar-auth-refresh', $refreshToken, time() + $this->config->get('refresh'), '/', $_SERVER['HTTP_HOST'], true, true);
        }
    }

    /**
     * Refreshes the JWT token.
     *
     * @param array<string, mixed> $JWTBody the JWT body
     *
     * @return array<string, mixed>|bool the JWT body or false
     */
    // public function refresh(string $refreshToken, ?array &$JWTBody = null): array|bool
    // {
    //     if (!$this->config->get('jwt.refresh')) {
    //         return false;
    //     }
    //     $this->validateToken($refreshToken, $JWTRefreshBody);
    //     // $auth = $this->queryAuth($JWTRefreshBody['sub']);
    //     if (!$this->validateToken($refreshToken, $JWTRefreshBody, $this->buildRefreshTokenKey($auth['credential']))) {
    //         return false;
    //     }

    //     return $this->authorise($auth, $JWTBody);
    // }

    /**
     * Validates the JWT token.
     *
     * @param string               $token      the JWT token
     * @param array<string, mixed> $JWTBody    the JWT body
     * @param string               $passphrase the passphrase
     */
    protected function validateToken(string $token, ?array &$JWTBody = null, ?string $passphrase = null): bool
    {
        if (!$token || false === strpos($token, '.')) {
            return false;
        }
        list($JWTHeader, $JWTBody, $token_signature) = explode('.', $token, 3);
        $JWTHeader = json_decode(base64url_decode($JWTHeader), true);
        $JWTBody = json_decode(base64url_decode($JWTBody), true);
        if (!(is_array($JWTHeader)
            && is_array($JWTBody)
            && array_key_exists('alg', $JWTHeader)
            && array_key_exists('typ', $JWTHeader)
            && 'JWT' === $JWTHeader['typ'])) {
            return false;
        }
        if ($token_signature !== $this->sign($JWTHeader, $JWTBody, $passphrase)) {
            return false;
        }
        if (!($JWTBody['iss'] === $this->config->get('issuer')
            && $JWTBody['exp'] > time())) {
            return false;
        }

        return true;
    }

    private function checkToken(): bool
    {
        if (null !== $this->token && array_key_exists('token', $this->token)) {
            $token = $this->token['token'];
        } elseif (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)
            && 'bearer ' === strtolower(substr($_SERVER['HTTP_AUTHORIZATION'], 0, 7))) {
            $token = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
        } elseif (array_key_exists('hazaar-auth-token', $_COOKIE)) {
            $token = $_COOKIE['hazaar-auth-token'];
        }
        if (isset($token)
            && $this->validateToken($token, $JWTBody)) {
            $this->data = $JWTBody;
            $this->data['identity'] = $JWTBody['sub'];

            return true;
        }
        // if (array_key_exists('hazaar-auth-refresh', $_COOKIE)) {
        //     $refreshToken = $_COOKIE['hazaar-auth-refresh'];
        //     if ($this->refresh($refreshToken, $JWTBody)) {
        //         $this->data = $JWTBody;

        //         return true;
        //     }
        // }

        return false;
    }

    /**
     * Builds a JWT token with the given JWT body and passphrase.
     *
     * @param array<string,mixed> $JWTBody    the body of the JWT token
     * @param null|string         $passphrase The passphrase to sign the JWT token. Defaults to null.
     *
     * @return string the JWT token
     */
    private function buildToken(array $JWTBody, ?string $passphrase = null): string
    {
        $JWTHeader = [
            'alg' => $this->config->get('alg'),
            'typ' => 'JWT',
        ];

        return base64url_encode(json_encode($JWTHeader))
            .'.'.base64url_encode(json_encode($JWTBody))
            .'.'.$this->sign($JWTHeader, $JWTBody, $passphrase);
    }

    private function buildRefreshTokenKey(string $credential): string
    {
        $fingerprint = $_SERVER['HTTP_USER_AGENT'];
        if ($this->config->get('fingerprintIP')
            && ($app = Application::getInstance())
            && ($request = $app->request) instanceof HTTP
            && ($clientIP = $request->getRemoteAddr())) {
            $fingerprint .= ':'.$clientIP;
        }

        return hash_hmac('sha256', $fingerprint, $credential);
    }

    /**
     * Signs the JWT token.
     *
     * @param array<string,mixed> $JWTHeader the header of the JWT token
     * @param array<string,mixed> $JWTBody   the body of the JWT token
     *
     * @return string the signature
     */
    private function sign(array $JWTHeader, array $JWTBody, ?string $passphrase = null): string
    {
        if (!array_key_exists('alg', $JWTHeader)) {
            throw new \Exception('No algorithm set for JWT signing');
        }
        if (!preg_match('/^(HS|RS|ES|PS)(256|384|512)$/', $JWTHeader['alg'], $matches)) {
            throw new \Exception('Unsupported JWT signing algorithm');
        }
        $alg = $passphrase ? 'HS' : $matches[1];
        $bits = $matches[2];
        $signature = null;
        if (!$passphrase) {
            $passphrase = $this->passphrase;
        }
        $token_content = base64url_encode(json_encode($JWTHeader)).'.'.base64url_encode(json_encode($JWTBody));

        switch ($alg) {
            case 'HS':
                if (!$passphrase) {
                    throw new \Exception('No passphrase set for JWT signing');
                }
                $algo_const = 'sha'.$bits;
                if (!in_array($algo_const, hash_hmac_algos())) {
                    throw new \Exception('Unsupported JWT signing algorithm');
                }
                $signature = hash_hmac('sha'.$bits, $token_content, $passphrase);

                break;

            case 'RS':
            case 'ES':
            case 'PS':
                if (!$this->privateKey) {
                    throw new \Exception('No private key set for JWT signing');
                }
                $algos = openssl_get_md_methods();
                if (!in_array('sha'.$bits, $algos)) {
                    throw new \Exception('Unsupported JWT signing algorithm');
                }
                $algo_const = null;

                switch ($bits) {
                    case '256':
                        $algo_const = OPENSSL_ALGO_SHA256;

                        break;

                    case '384':
                        $algo_const = OPENSSL_ALGO_SHA384;

                        break;

                    case '512':
                        $algo_const = OPENSSL_ALGO_SHA512;

                        break;
                }
                if (!$algo_const) {
                    throw new \Exception('Unsupported JWT signing algorithm');
                }
                $signing_result = openssl_sign($token_content, $signature, trim($this->privateKey), $algo_const);
                if (true !== $signing_result) {
                    throw new \Exception(openssl_error_string());
                }

                break;
        }
        if (!$signature) {
            throw new \Exception('Unable to sign JWT');
        }

        return base64url_encode($signature);
    }
}
