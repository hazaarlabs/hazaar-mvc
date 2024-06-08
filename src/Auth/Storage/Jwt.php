<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

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
abstract class JWT implements Storage, \ArrayAccess
{
    protected ?string $passphrase = null;
    protected string $privateKey;

    /**
     * @var array<mixed>
     */
    protected ?array $token = null;

    /**
     * @var array<mixed>
     */
    protected array $data = [];
    private Map $config;

    public function __construct(?Map $config = null)
    {
        $this->config = $config;
        $this->config->enhance([
            'alg' => 'HS256',
            'issuer' => 'hazaar-auth',
            'timeout' => 3600,
            'refresh' => 86400,
            'fingerprintIP' => true,
        ]);
        if ($this->config->has('jwt.passphrase')) {
            $this->passphrase = $this->config->get('jwt.passphrase');
        }
        if ($this->config->has('jwt.privateKey')) {
            $this->privateKey = $this->config->get('jwt.privateKey');
        } elseif ($this->config->has('jwt.privateKeyFile')) {
            if ($privateKeyFile = Loader::getFilePath(FILE_PATH_CONFIG, $this->config->get('jwt.privateKeyFile'))) {
                $this->privateKey = file_get_contents($privateKeyFile);
            }
        }
        if (!isset($this->privateKey)) {
            throw new \Exception('No private key set for JWT signing');
        }
    }

    /**
     * Magic Functions.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function __get(string $name): mixed
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    // public function authenticate(?string $identity = null, ?string $credential = null, bool $autologin = false): mixed
    // {
    //     $auth = parent::authenticate($identity, $credential, $autologin);
    //     if (!is_array($auth)) {
    //         return false;
    //     }
    //     if (!($token = $this->authorise($auth, $jwt_body))) {
    //         return false;
    //     }
    //     $this->data = $jwt_body;

    //     return $this->token = $token;
    // }

    // public function authenticated(): bool
    // {
    //     if (null !== $this->token && array_key_exists('token', $this->token)) {
    //         $token = $this->token['token'];
    //     } elseif (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)
    //         && 'bearer ' === strtolower(substr($_SERVER['HTTP_AUTHORIZATION'], 0, 7))) {
    //         $token = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
    //     } elseif (array_key_exists('hazaar-auth-token', $_COOKIE)) {
    //         $token = $_COOKIE['hazaar-auth-token'];
    //     }
    //     if (isset($token)
    //         && $this->validateToken($token, $jwt_body)) {
    //         $this->data = $jwt_body;

    //         return true;
    //     }
    //     if (array_key_exists('hazaar-auth-refresh', $_COOKIE)) {
    //         $refresh_token = $_COOKIE['hazaar-auth-refresh'];
    //         if ($this->refresh($refresh_token, $jwt_body)) {
    //             $this->data = $jwt_body;

    //             return true;
    //         }
    //     }

    //     return false;
    // }

    public function deauth(): bool
    {
        setcookie('hazaar-auth-token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
        setcookie('hazaar-auth-refresh', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);

        return true;
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

    /**
     * Authorises the JWT token.
     *
     * @param array<string,mixed> $data
     */
    public function write(array $data): void
    {
        $out = [];
        $JWTBody = array_merge([
            'iss' => $this->config->get('jwt.issuer'),
            'iat' => time(),
            'exp' => time() + $this->config->get('jwt.timeout'),
            'sub' => $data['identity'],
        ], $data['data']);
        $token = $this->buildToken($JWTBody);
        setcookie('hazaar-auth-token', $token, time() + $this->config->get('timeout'), '/', $_SERVER['HTTP_HOST'], true, true);
        if ($this->config->get('refresh')) {
            $refreshToken = $this->buildToken([
                'iss' => $JWTBody['iss'],
                'iat' => $JWTBody['iat'],
                'exp' => $JWTBody['iat'] + $this->config->get('jwt.refresh'),
                'sub' => $data['identity'],
            ], $this->buildRefreshTokenKey($data['credential']));
            setcookie('hazaar-auth-refresh', $refreshToken, time() + $this->config->get('refresh'), '/', $_SERVER['HTTP_HOST'], true, true);
        }
    }

    /**
     * Refreshes the JWT token.
     *
     * @param array<string, mixed> $jwt_body the JWT body
     *
     * @return array<string, mixed>|bool the JWT body or false
     */
    // public function refresh(string $refresh_token, ?array &$jwt_body = null): array|bool
    // {
    //     if (!$this->config->get('jwt.refresh')) {
    //         return false;
    //     }
    //     $this->validateToken($refresh_token, $JWTRefreshBody);
    //     $auth = $this->queryAuth($JWTRefreshBody['sub']);
    //     if (!$this->validateToken($refresh_token, $JWTRefreshBody, $this->buildRefreshTokenKey($auth['credential']))) {
    //         return false;
    //     }

    //     return $this->authorise($auth, $jwt_body);
    // }

    /**
     * Validates the JWT token.
     *
     * @param string               $token      the JWT token
     * @param array<string, mixed> $jwt_body   the JWT body
     * @param string               $passphrase the passphrase
     */
    protected function validateToken(string $token, ?array &$jwt_body = null, ?string $passphrase = null): bool
    {
        if (!$token || false === strpos($token, '.')) {
            return false;
        }
        list($jwt_header, $jwt_body, $token_signature) = explode('.', $token, 3);
        $jwt_header = json_decode(base64url_decode($jwt_header), true);
        $jwt_body = json_decode(base64url_decode($jwt_body), true);
        if (!(is_array($jwt_header)
            && is_array($jwt_body)
            && array_key_exists('alg', $jwt_header)
            && array_key_exists('typ', $jwt_header)
            && 'JWT' === $jwt_header['typ'])) {
            return false;
        }
        if ($token_signature !== $this->sign($jwt_header, $jwt_body, $passphrase)) {
            return false;
        }
        if (!($jwt_body['iss'] === $this->config->get('jwt.issuer')
            && $jwt_body['exp'] > time())) {
            return false;
        }

        return true;
    }

    /**
     * Builds a JWT token with the given JWT body and passphrase.
     *
     * @param array<string,mixed> $jwt_body   the body of the JWT token
     * @param null|string         $passphrase The passphrase to sign the JWT token. Defaults to null.
     *
     * @return string the JWT token
     */
    private function buildToken(array $jwt_body, ?string $passphrase = null): string
    {
        $jwt_header = [
            'alg' => $this->config->get('jwt.alg'),
            'typ' => 'JWT',
        ];

        return base64url_encode(json_encode($jwt_header))
            .'.'.base64url_encode(json_encode($jwt_body))
            .'.'.$this->sign($jwt_header, $jwt_body, $passphrase);
    }

    private function buildRefreshTokenKey(string $credential): string
    {
        $fingerprint = $_SERVER['HTTP_USER_AGENT'];
        if ($this->config->get('jwt.fingerprintIP')
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
     * @param array<string,mixed> $jwt_header the header of the JWT token
     * @param array<string,mixed> $jwt_body   the body of the JWT token
     *
     * @return string the signature
     */
    private function sign(array $jwt_header, array $jwt_body, ?string $passphrase = null): string
    {
        if (!array_key_exists('alg', $jwt_header)) {
            throw new \Exception('No algorithm set for JWT signing');
        }
        if (!preg_match('/^(HS|RS|ES|PS)(256|384|512)$/', $jwt_header['alg'], $matches)) {
            throw new \Exception('Unsupported JWT signing algorithm');
        }
        $alg = $passphrase ? 'HS' : $matches[1];
        $bits = $matches[2];
        $signature = null;
        if (!$passphrase) {
            $passphrase = $this->passphrase;
        }
        $token_content = base64url_encode(json_encode($jwt_header)).'.'.base64url_encode(json_encode($jwt_body));

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
