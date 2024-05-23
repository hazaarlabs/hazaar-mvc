<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Application;
use Hazaar\Application\Request\HTTP;
use Hazaar\Auth\Adapter;
use Hazaar\Loader;
use Hazaar\Map;

/**
 * JWT Authentication Adapter.
 *
 * This class provides a JWT authentication adapter for the Hazaar MVC framework.
 *
 * @implements \ArrayAccess<string, mixed>
 */
abstract class JWT extends Adapter implements \ArrayAccess
{
    protected ?string $passphrase = null;
    protected string $privateKey;

    /**
     * @var array<mixed>
     */
    protected array $token;

    /**
     * @var array<mixed>
     */
    protected array $data = [];

    public function __construct(array|Map $config)
    {
        $options = [
            'jwt' => [
                'alg' => 'HS256',
                'issuer' => 'hazaar-auth',
                'timeout' => 3600,
                'refresh' => 86400,
                'fingerprintIP' => true,
            ],
        ];
        parent::__construct(Map::_($options, $config));
        if ($this->options->has('jwt.passphrase')) {
            $this->passphrase = $this->options->get('jwt.passphrase');
        }
        if ($this->options->has('jwt.privateKey')) {
            $this->privateKey = $this->options->get('jwt.privateKey');
        } elseif ($this->options->has('jwt.privateKeyFile')) {
            if ($privateKeyFile = Loader::getFilePath(FILE_PATH_CONFIG, $this->options->get('jwt.privateKeyFile'))) {
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

    public function authenticate(?string $identity = null, ?string $credential = null, bool $autologin = false): mixed
    {
        $auth = parent::authenticate($identity, $credential, $autologin);
        if (!is_array($auth)) {
            return false;
        }
        if (!($token = $this->authorise($auth, $jwt_body))) {
            return false;
        }
        $this->data = $jwt_body;

        return $this->token = $token;
    }

    public function authenticated(): bool
    {
        if (is_array($this->token) && array_key_exists('token', $this->token)) {
            $token = $this->token['token'];
        } elseif (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)
            && 'bearer ' === strtolower(substr($_SERVER['HTTP_AUTHORIZATION'], 0, 7))) {
            $token = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
        } elseif (array_key_exists('hazaar-auth-token', $_COOKIE)) {
            $token = $_COOKIE['hazaar-auth-token'];
        }
        if (isset($token)
            && $this->validateToken($token, $jwt_body)) {
            $this->data = $jwt_body;

            return true;
        }
        if (array_key_exists('hazaar-auth-refresh', $_COOKIE)) {
            $refresh_token = $_COOKIE['hazaar-auth-refresh'];
            if ($this->refresh($refresh_token, $jwt_body)) {
                $this->data = $jwt_body;

                return true;
            }
        }

        return false;
    }

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
     * Refreshes the JWT token.
     *
     * @param string               $refresh_token the refresh token
     * @param array<string, mixed> $jwt_body      the JWT body
     *
     * @return array<string, mixed>|bool the JWT body or false
     */
    public function refresh(string $refresh_token, ?array &$jwt_body = null): array|bool
    {
        if (!$this->options->get('jwt.refresh')) {
            return false;
        }
        $this->validateToken($refresh_token, $jwt_refresh_body);
        $auth = $this->queryAuth($jwt_refresh_body['sub']);
        if (!$this->validateToken($refresh_token, $jwt_refresh_body, $this->buildRefreshTokenKey($auth['credential']))) {
            return false;
        }

        return $this->authorise($auth, $jwt_body);
    }

    protected function canAutoLogin(): bool
    {
        return false;
    }

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
        if (!($jwt_body['iss'] === $this->options->get('jwt.issuer')
            && $jwt_body['exp'] > time())) {
            return false;
        }

        return true;
    }

    /**
     * Authorises the JWT token.
     *
     * @param array<string, mixed> $auth     the auth
     * @param array<string, mixed> $jwt_body the JWT body
     *
     * @return array<string, mixed> the JWT token
     */
    private function authorise(array $auth, ?array &$jwt_body = null): array
    {
        $out = [];
        $jwt_body = array_merge([
            'iss' => $this->options->get('jwt.issuer'),
            'iat' => time(),
            'exp' => time() + $this->options->get('jwt.timeout'),
            'sub' => $auth['identity'],
        ], $auth['data']);
        $out['token'] = $this->buildToken($jwt_body);
        // setcookie('hazaar-auth-token', $token, time() + $this->options->get('jwt.timeout'), '/', $_SERVER['HTTP_HOST'], true, true);
        if ($this->options->get('jwt.refresh')) {
            $out['refresh'] = $this->buildToken([
                'iss' => $jwt_body['iss'],
                'iat' => $jwt_body['iat'],
                'exp' => $jwt_body['iat'] + $this->options->get('jwt.refresh'),
                'sub' => $auth['identity'],
            ], $this->buildRefreshTokenKey($auth['credential']));
            // setcookie('hazaar-auth-refresh', $refresh_token, time() + $this->options->get('jwt.refresh'), '/', $_SERVER['HTTP_HOST'], true, true);
        }

        return $out;
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
            'alg' => $this->options->get('jwt.alg'),
            'typ' => 'JWT',
        ];

        return base64url_encode(json_encode($jwt_header))
            .'.'.base64url_encode(json_encode($jwt_body))
            .'.'.$this->sign($jwt_header, $jwt_body, $passphrase);
    }

    private function buildRefreshTokenKey(string $credential): string
    {
        $fingerprint = $_SERVER['HTTP_USER_AGENT'];
        if ($this->options->get('jwt.fingerprintIP')
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
