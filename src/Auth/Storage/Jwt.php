<?php

declare(strict_types=1);

namespace Hazaar\Auth\Storage;

use Hazaar\Application;
use Hazaar\Application\Request\HTTP;
use Hazaar\Auth\Adapter;
use Hazaar\Auth\Interfaces\Storage;
use Hazaar\Auth\Storage\Exception\JWTPrivateKeyFileNotFound;
use Hazaar\Auth\Storage\Exception\NoApplication;
use Hazaar\Auth\Storage\Exception\NoJWTAlgorithm;
use Hazaar\Auth\Storage\Exception\NoJWTPassphrase;
use Hazaar\Auth\Storage\Exception\NoJWTPrivateKey;
use Hazaar\Auth\Storage\Exception\UnsupportedJWTAlgorithm;
use Hazaar\Loader;
use Hazaar\Map;

/**
 * JWT Authentication Adapter.
 *
 * This class provides a JWT authentication adapter for the Hazaar MVC framework.
 */
class JWT implements Storage
{
    protected ?string $passphrase = null;
    protected ?string $privateKey = null;

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
    private bool $clearCookie = false;

    public function __construct(?Map $config = null)
    {
        if (!($app = Application::getInstance())) {
            throw new NoApplication('JWT');
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
            $privateKeyFile = Loader::getFilePath(FILE_PATH_CONFIG, $this->config->get('privateKeyFile', 'private_key.pem'));
            if ($privateKeyFile && is_readable($privateKeyFile) && is_file($privateKeyFile)) {
                $this->privateKey = @file_get_contents($privateKeyFile);
            } else {
                throw new JWTPrivateKeyFileNotFound($this->config->get('privateKeyFile'));
            }
        }
        $this->checkToken();
    }

    public function isEmpty(): bool
    {
        return null === $this->data || !isset($this->data['identity']);
    }

    public function read(): array
    {
        return $this->data ?? [];
    }

    /**
     * Authorises the JWT token.
     *
     * @param array<string,mixed> $data
     */
    public function write(array $data): void
    {
        if (!isset($data['data'])) {
            $data['data'] = [];
        }
        $this->data = $data;
        $this->writeCookie = true;
    }

    public function has(string $key): bool
    {
        if ('identity' === $key) {
            return isset($this->data['identity']);
        }

        return isset($this->data['data'][$key]);
    }

    public function get(string $key)
    {
        if ('identity' === $key) {
            return $this->data['identity'];
        }

        return $this->data['data'][$key] ?? null;
    }

    public function set(string $key, $value): void
    {
        if ('identity' !== $key) {
            $this->data['data'][$key] = $value;
            $this->writeCookie = true;
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
        if (false === $this->isEmpty()) {
            $this->data = null;
            $this->clearCookie = true;
        }
    }

    public function getToken(): ?array
    {
        $out = [];
        $JWTBody = $this->data['data'] = array_merge(
            $this->data['data'],
            [
                'iss' => $this->config->get('issuer'),
                'iat' => time(),
                'exp' => time() + $this->config->get('timeout'),
                'sub' => $this->data['identity'],
            ]
        );
        $out['token'] = $this->buildToken($JWTBody);
        if ($this->config->get('refresh')) {
            $out['refresh'] = $this->buildToken(array_merge($JWTBody, [
                'exp' => $JWTBody['iat'] + $this->config->get('refresh'),
            ]), $this->buildRefreshTokenKey($this->passphrase));
        }
        $this->writeCookie = false;

        return $out;
    }

    public function writeToken(): void
    {
        if (true === $this->clearCookie) {
            setcookie('hazaar-auth-token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
            setcookie('hazaar-auth-refresh', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
        } elseif (true === $this->writeCookie) {
            $tokens = $this->getToken();
            setcookie('hazaar-auth-token', $tokens['token'], time() + $this->config->get('timeout'), '/', $_SERVER['HTTP_HOST'], true, true);
            if (isset($tokens['refresh'])) {
                setcookie('hazaar-auth-refresh', $tokens['refresh'], time() + $this->config->get('refresh'), '/', $_SERVER['HTTP_HOST'], true, true);
            }
        }
    }

    /**
     * Refreshes the JWT token.
     *
     * @param array<string, mixed> $JWTRefreshBody the JWT body
     */
    private function refresh(string $refreshToken, ?array &$JWTRefreshBody = null): bool
    {
        if (!$this->config->get('refresh')) {
            return false;
        }
        if (!$this->validateToken($refreshToken, $JWTRefreshBody, $this->buildRefreshTokenKey($this->passphrase))) {
            return false;
        }

        return true;
    }

    /**
     * Validates the JWT token.
     *
     * @param string               $token      the JWT token
     * @param array<string, mixed> $JWTBody    the JWT body
     * @param string               $passphrase the passphrase
     */
    private function validateToken(string $token, ?array &$JWTBody = null, ?string $passphrase = null): bool
    {
        if (!$token || false === strpos($token, '.')) {
            return false;
        }
        list($JWTHeader, $JWTBody, $tokenSignature) = explode('.', $token, 3);
        $JWTHeader = json_decode(base64url_decode($JWTHeader), true);
        $JWTBody = json_decode(base64url_decode($JWTBody), true);
        if (!(is_array($JWTHeader)
            && is_array($JWTBody)
            && array_key_exists('alg', $JWTHeader)
            && array_key_exists('typ', $JWTHeader)
            && 'JWT' === $JWTHeader['typ'])) {
            return false;
        }
        if ($tokenSignature !== $this->sign($JWTHeader, $JWTBody, $passphrase)) {
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
            $this->data = ['data' => $JWTBody];
            $this->data['identity'] = $JWTBody['sub'];

            return true;
        }
        if (array_key_exists('hazaar-auth-refresh', $_COOKIE)) {
            $refreshToken = $_COOKIE['hazaar-auth-refresh'];
            if ($this->refresh($refreshToken, $JWTBody)) {
                $this->data = ['data' => $JWTBody];
                $this->data['identity'] = $JWTBody['sub'];
                $this->writeCookie = true;

                return true;
            }
        }

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

    private function buildRefreshTokenKey(string $passphrase): string
    {
        $fingerprint = $_SERVER['HTTP_USER_AGENT'];
        if ($this->config->get('fingerprintIP')
            && ($app = Application::getInstance())
            && ($request = $app->request) instanceof HTTP
            && ($clientIP = $request->getRemoteAddr())) {
            $fingerprint .= ':'.$clientIP;
        }

        return hash_hmac('sha256', $fingerprint, $passphrase);
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
            throw new NoJWTAlgorithm();
        }
        if (!preg_match('/^(HS|RS|ES|PS)(256|384|512)$/', $JWTHeader['alg'], $matches)) {
            throw new UnsupportedJWTAlgorithm($JWTHeader['alg']);
        }
        $alg = $passphrase ? 'HS' : $matches[1];
        $bits = $matches[2];
        $signature = null;
        if (!$passphrase) {
            $passphrase = $this->passphrase;
        }
        $tokenContent = base64url_encode(json_encode($JWTHeader)).'.'.base64url_encode(json_encode($JWTBody));

        switch ($alg) {
            case 'HS':
                if (!$passphrase) {
                    throw new NoJWTPassphrase();
                }
                $algoConst = 'sha'.$bits;
                if (!in_array($algoConst, hash_hmac_algos())) {
                    throw new UnsupportedJWTAlgorithm($algoConst);
                }
                $signature = hash_hmac($algoConst, $tokenContent, $passphrase);

                break;

            case 'RS':
            case 'ES':
            case 'PS':
                if (!$this->privateKey) {
                    throw new NoJWTPrivateKey();
                }
                $algos = openssl_get_md_methods();
                $algoName = 'sha'.$bits;
                if (!in_array($algoName, $algos)) {
                    throw new UnsupportedJWTAlgorithm($algoName);
                }

                switch ($bits) {
                    case '256':
                        $algoConst = OPENSSL_ALGO_SHA256;

                        break;

                    case '384':
                        $algoConst = OPENSSL_ALGO_SHA384;

                        break;

                    case '512':
                        $algoConst = OPENSSL_ALGO_SHA512;

                        break;

                    default:
                        throw new UnsupportedJWTAlgorithm($algoName);
                }
                $signingResult = openssl_sign($tokenContent, $signature, trim($this->privateKey), $algoConst);
                if (true !== $signingResult) {
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
