<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Auth\Adapter\Exception\HTPasswdFileMissing;
use Hazaar\Auth\Interfaces\Adapter;
use Hazaar\Cache;
use Hazaar\Exception;
use Hazaar\Map;

class HTPasswd extends Session implements Adapter
{
    private string $passwd = CONFIG_PATH.DIRECTORY_SEPARATOR.'.passwd';
    private string $user_hash = '$apr1$';

    /**
     * Construct the new authentication object with the field names.
     *
     * @param array<mixed>|Map $cacheConfig The configuration options
     */
    public function __construct(?string $file = null, array|Map $cacheConfig = [], ?Cache $cacheBackend = null)
    {
        if (null !== $file) {
            $this->passwd = $file;
        }
        if (!file_exists($this->passwd)) {
            throw new HTPasswdFileMissing($this->passwd);
        }
        parent::__construct($cacheConfig, $cacheBackend);
    }

    /**
     * Query the authentication adapter.
     *
     * We must provide a queryAuth method for the auth base class to use to look up details
     *
     * @param array<mixed> $extras
     *
     * @return array<mixed>|bool The result object
     */
    public function queryAuth(string $identity, array $extras = []): array|bool
    {
        $users = [];
        $lines = explode("\n", trim(file_get_contents($this->passwd)));
        foreach ($lines as $line) {
            if (!$line) {
                continue;
            }
            list($user_identity, $userhash) = explode(':', $line);
            $users[$user_identity] = $userhash;
        }
        $this->user_hash = trim(ake($users, $identity, ''));
        if (strlen($this->user_hash) > 0) {
            return ['identity' => $identity, 'credential' => $this->user_hash];
        }

        return false;
    }

    public function getCredential(?string $credential = null): string
    {
        if (null === $credential) {
            $credential = $this->credential;
        }
        $hash = '';
        if ('$apr1$' == substr($this->user_hash, 0, 6)) {                      // APR1-MD5
            $BASE64_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
            $APRMD5_ALPHABET = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            $parts = explode('$', $this->user_hash);
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
        } elseif ('{SHA}' == substr($this->user_hash, 0, 5)) {                  // SHA1
            $hash = '{SHA}'.base64_encode(sha1($credential, true));
        } elseif ('$2y$' == substr($this->user_hash, 0, 4)) {                   // Blowfish
            $hash = crypt($credential, substr($this->user_hash, 0, 29));
        } else {
            throw new Exception('Unsupported password encryption algorithm.');
        }

        return $hash;
    }
}
