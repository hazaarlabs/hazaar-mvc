<?php

namespace Hazaar\Auth\Adapter;

abstract class JWT extends \Hazaar\Auth\Adapter implements \ArrayAccess
{
    private $passphrase = null;
    private $private_key = null;
    private $data = [];

    public function __construct($config)
    {
        $options = [
            'jwt' => [
                'alg' => 'HS256',
                'issuer' => 'hazaar-auth',
                'timeout' => 3600,
                'refresh' => 86400,
                'fingerprintIP' => true
            ]
        ];
        parent::__construct($options);
        $this->options->jwt->extend($config);
        if ($this->options->has('jwt.passphrase')) {
            $this->passphrase = $this->options->get('jwt.passphrase');
        }
        if ($this->options->has('privateKey')) {
            $this->private_key = $this->options->get('jwt.privateKey');
        } elseif ($this->options->has('jwt.privateKeyFile')) {
            if ($private_key_file = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, $this->options->get('jwt.privateKeyFile'))) {
                $this->private_key = file_get_contents($private_key_file);
            }
        }
    }

    public function authenticate($identity = null, $credential = null, $autologin = false)
    {
        $auth = parent::authenticate($identity, $credential, $autologin);
        if (!is_array($auth)) {
            return false;
        }
        if (!$this->authorise($auth, $jwt_body)) {
            return false;
        }
        $this->data = $jwt_body;
        return true;
    }

    public function authenticated()
    {
        if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)
            && strtolower(substr($_SERVER['HTTP_AUTHORIZATION'], 0, 7)) === 'bearer ') {
            $token = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
        } elseif (array_key_exists('hazaar-auth-token', $_COOKIE)) {
            $token = $_COOKIE['hazaar-auth-token'];
        }
        if (isset($token)
            && $this->validateToken($token, $jwt_body)) {
            $this->data = $jwt_body;
            return true;
        } elseif (array_key_exists('hazaar-auth-refresh', $_COOKIE)) {
            $refresh_token = $_COOKIE['hazaar-auth-refresh'];
            if ($this->refresh($refresh_token, $jwt_body)) {
                $this->data = $jwt_body;
                return true;
            }
        }
        return false;
    }

    public function deauth()
    {
        setcookie('hazaar-auth-token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
        setcookie('hazaar-auth-refresh', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
        return true;
    }

    private function refresh($refresh_token, &$jwt_body = null)
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

    private function authorise($auth, &$jwt_body = null)
    {
        $jwt_body = array_merge([
            'iss' => $this->options->get('jwt.issuer'),
            'iat' => time(),
            'exp' => time() + $this->options->get('jwt.timeout'),
            'sub' => $auth['identity']
        ], $auth['data']);
        $token = $this->buildToken($jwt_body);
        setcookie('hazaar-auth-token', $token, time() + $this->options->get('jwt.timeout'), '/', $_SERVER['HTTP_HOST'], true, true);
        if ($this->options->get('jwt.refresh')) {
            $refresh_token = $this->buildToken([
                'iss' => $jwt_body['iss'],
                'iat' => $jwt_body['iat'],
                'exp' => $jwt_body['iat'] + $this->options->get('jwt.refresh'),
                'sub' => $auth['identity']
            ], $this->buildRefreshTokenKey($auth['credential']));
            setcookie('hazaar-auth-refresh', $refresh_token, time() + $this->options->get('jwt.refresh'), '/', $_SERVER['HTTP_HOST'], true, true);
        }
        return true;
    }

    private function buildToken($jwt_body, $passphrase = null)
    {
        $jwt_header = [
            'alg' => $this->options->get('jwt.alg'),
            'typ' => 'JWT'
        ];
        return base64url_encode(json_encode($jwt_header))
            . '.' . base64url_encode(json_encode($jwt_body))
            . '.' . $this->sign($jwt_header, $jwt_body, $passphrase);
    }

    protected function validateToken($token, &$jwt_body = null, $passphrase = null)
    {
        list($jwt_header, $jwt_body, $token_signature) = explode('.', $token, 3);
        $jwt_header = json_decode(base64url_decode($jwt_header), true);
        $jwt_body = json_decode(base64url_decode($jwt_body), true);
        if (!(is_array($jwt_header)
            && is_array($jwt_body)
            && array_key_exists('alg', $jwt_header)
            && array_key_exists('typ', $jwt_header)
            && $jwt_header['typ'] === 'JWT')) {
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

    private function buildRefreshTokenKey($credential)
    {
        $clientIP =  getenv('HTTP_CLIENT_IP') ?:
            getenv('HTTP_X_FORWARDED_FOR') ?:
            getenv('HTTP_X_FORWARDED') ?:
            getenv('HTTP_FORWARDED_FOR') ?:
            getenv('HTTP_FORWARDED') ?:
            getenv('REMOTE_ADDR');
        $fingerprint = $_SERVER['HTTP_USER_AGENT'];
        if ($this->options->get('jwt.fingerprintIP')) {
            $fingerprint .= ':' . $clientIP;
        }
        return hash_hmac('sha256', $fingerprint, $credential);
    }

    private function sign($jwt_header, $jwt_body, $passphrase = null)
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
        $token_content = base64url_encode(json_encode($jwt_header)) . '.' . base64url_encode(json_encode($jwt_body));
        switch($alg) {
            case 'HS':
                if (!$passphrase) {
                    throw new \Exception('No passphrase set for JWT signing');
                }
                $algo_const = 'sha' . $bits;
                if (!in_array($algo_const, hash_hmac_algos())) {
                    throw new \Exception('Unsupported JWT signing algorithm');
                }
                $signature = hash_hmac('sha' . $bits, $token_content, $passphrase);
                break;
            case 'RS':
            case 'ES':
            case 'PS':
                if (!$this->private_key) {
                    throw new \Exception('No private key set for JWT signing');
                }
                $algo_const = null;
                switch($bits) {
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
                openssl_sign($token_content, $signature, trim($this->private_key), $algo_const);
                break;
        }
        if (!$signature) {
            throw new \Exception('Unable to sign JWT');
        }
        return base64url_encode($signature);
    }

    /**
     * ArrayAccess Functions
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
     * Magic Functions
     */
    public function __set($name, $value): void
    {
        $this->data[$name] = $value;
    }

    public function __get($name): mixed
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    public function __isset($name): bool
    {
        return isset($this->data[$name]);
    }

    public function __unset($name): void
    {
        unset($this->data[$name]);
    }
}
