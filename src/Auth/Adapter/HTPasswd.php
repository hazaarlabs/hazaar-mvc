<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Auth\Adapter;
use Hazaar\Auth\Adapter\Exception\HTPasswdFileMissing;
use Hazaar\Map;

class HTPasswd extends Adapter
{
    private string $passwd = CONFIG_PATH.DIRECTORY_SEPARATOR.'.passwd';
    private string $userHash = '$apr1$';

    /**
     * Construct the new authentication object with the field names.
     */
    public function __construct(null|array|Map $config = [])
    {
        parent::__construct($config);
        if ($this->options->has('passwdFile')) {
            $this->passwd = $this->options->get('passwdFile');
        } else {
            $this->passwd = CONFIG_PATH.DIRECTORY_SEPARATOR.'.passwd';
        }
        if (!file_exists($this->passwd)) {
            throw new HTPasswdFileMissing($this->passwd);
        }
    }

    /**
     * Query the authentication adapter.
     *
     * We must provide a queryAuth method for the auth base class to use to look up details
     *
     * @param string        $identity The identity
     * @param array<string> $extra    Extra data to return with the authentication
     *
     * @return array<mixed>|bool The result object
     */
    public function queryAuth(string $identity, array $extra = []): array|bool
    {
        $lines = explode("\n", trim(file_get_contents($this->passwd)));
        foreach ($lines as $line) {
            if (!$line) {
                continue;
            }
            list($userIdentity, $userHash) = explode(':', $line);
            if ($userIdentity === $identity) {
                $this->userHash = $userHash;

                return ['identity' => $identity, 'credential' => $this->userHash];
            }
        }

        return false;
    }

    public function getCredentialHash(?string $credential = null): string
    {
        if (null === $credential) {
            $credential = $this->credential;
        }
        $hash = '';
        if ('$apr1$' == substr($this->userHash, 0, 6)) { // APR1-MD5
            $BASE64_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
            $APRMD5_ALPHABET = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            $parts = explode('$', $this->userHash);
            $salt = substr($parts[2], 0, 8);
            $max = strlen($credential);
            $context = $credential.'$apr1$'.$salt;
            $binary = pack('H32', md5($credential.$salt.$credential));
            for ($i = $max; $i > 0; $i -= 16) {
                $context .= substr($binary, 0, min(16, $i));
            }
            for ($i = $max; $i > 0; $i >>= 1) {
                $context .= ($i & 1) ? chr(0) : $credential[0];
            }
            $binary = pack('H32', md5($context));
            for ($i = 0; $i < 1000; ++$i) {
                $new = ($i & 1) ? $credential : $binary;
                if ($i % 3) {
                    $new .= $salt;
                }
                if ($i % 7) {
                    $new .= $credential;
                }
                $new .= ($i & 1) ? $binary : $credential;
                $binary = pack('H32', md5($new));
            }
            $hash = '';
            for ($i = 0; $i < 5; ++$i) {
                $k = $i + 6;
                $j = $i + 12;
                if (16 == $j) {
                    $j = 5;
                }
                $hash = $binary[$i].$binary[$k].$binary[$j].$hash;
            }
            $hash = chr(0).chr(0).$binary[11].$hash;
            $hash = strtr(strrev(substr(base64_encode($hash), 2)), $BASE64_ALPHABET, $APRMD5_ALPHABET);
            $hash = '$apr1$'.$salt.'$'.$hash;
        } elseif ('{SHA}' == substr($this->userHash, 0, 5)) { // SHA1
            $hash = '{SHA}'.base64_encode(sha1($credential, true));
        } elseif ('$2y$' == substr($this->userHash, 0, 4)) { // Blowfish
            $hash = crypt($credential, substr($this->userHash, 0, 29));
        } else {
            throw new \Exception('Unsupported password encryption algorithm.');
        }

        return $hash;
    }

    public function create(string $identity, string $credential): bool
    {
        $hash = $this->getCredentialHash($credential);
        $lines = explode("\n", trim(file_get_contents($this->passwd)));
        foreach ($lines as $line) {
            if (!$line) {
                continue;
            }
            list($userIdentity, $userHash) = explode(':', $line);
            if ($userIdentity === $identity) {
                return false;
            }
        }
        $lines[] = $identity.':'.$hash;

        return (bool) file_put_contents($this->passwd, implode("\n", $lines));
    }

    public function update(string $identity, string $credential): bool
    {
        $hash = $this->getCredentialHash($credential);
        $lines = explode("\n", trim(file_get_contents($this->passwd)));
        foreach ($lines as $index => $line) {
            if (!$line) {
                continue;
            }
            list($userIdentity, $userHash) = explode(':', $line);
            if ($userIdentity === $identity) {
                $lines[$index] = $identity.':'.$hash;

                break;
            }
        }

        return (bool) file_put_contents($this->passwd, implode("\n", $lines));
    }

    public function delete(string $identity): bool
    {
        $lines = explode("\n", trim(file_get_contents($this->passwd)));
        foreach ($lines as $index => $line) {
            if (!$line) {
                continue;
            }
            list($userIdentity, $userHash) = explode(':', $line);
            if ($userIdentity === $identity) {
                unset($lines[$index]);

                break;
            }
        }

        return (bool) file_put_contents($this->passwd, implode("\n", $lines));
    }
}
