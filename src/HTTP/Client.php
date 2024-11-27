<?php

declare(strict_types=1);

namespace Hazaar\HTTP;

use Hazaar\Auth\Adapter;
use Hazaar\Cache;
use Hazaar\Date;
use Hazaar\HTTP\Exception\RedirectNotAllowed;
use Hazaar\Loader;

class Client
{
    public static ?string $encryptionDefaultKey = null;
    public static string $encryptionDefaultCipher = 'AES-256-CBC';
    public static string $encryptionHeader = 'X-HAZAAR-COMKEY';
    private int $bufferSize = 4096;
    private int $connectionTimeout = 5;

    /**
     * @var array<string,array<string>|string>
     */
    private array $headers = [];

    /**
     * @var array<string,array<mixed>>
     */
    private array $cookies = [];
    private ?string $localCert = null;
    private ?string $certPassphrase = null;
    private bool $autoRedirect = true;

    /**
     * @var array<string>
     */
    private array $redirectMethods = [
        'GET',
        'OPTIONS',
        'PROPFIND',
    ];
    private ?string $username = null;
    private ?string $password = null;
    private ?Adapter $auth = null;
    private ?string $encryptionKey = null;
    private ?string $encryptionCipher = null;

    public function __construct(?string $localCert = null, ?string $passphrase = null)
    {
        $this->localCert = $localCert;
        $this->certPassphrase = $passphrase;
    }

    public function auth(string $username, string $password): void
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function authorisation(Adapter $auth): void
    {
        $this->auth = $auth;
    }

    public function setHeader(string $header, string $value): void
    {
        $this->headers[$header] = $value;
    }

    public function options(string $url): false|Response
    {
        return $this->send(new Request($url, 'OPTIONS'));
    }

    public function get(
        string|URL $url,
        int $redirectLimit = 10,
        int $offset = -1,
        ?int $maxlen = null
    ): false|Response {
        $request = new Request($url, 'GET');
        if ($offset >= 0) {
            $range = 'bytes='.$offset.'-';
            if ($maxlen) {
                $range .= ($offset + ($maxlen - 1));
            }
            $request->setHeader('Range', $range);
        }

        return $this->send($request, $redirectLimit);
    }

    public function head(string|URL $url, int $redirectLimit = 10): false|Response
    {
        return $this->send(new Request($url, 'HEAD'), $redirectLimit);
    }

    public function post(
        string|URL $url,
        mixed $data = null,
        ?string $dataType = null,
        int $redirectLimit = 0
    ): false|Response {
        $request = new Request($url, 'POST');
        if (!$dataType) {
            $dataType = 'text/html';
        }
        if ($data) {
            $request->setBody($data);
        }
        $request->setHeader('Content-Type', $dataType);

        return $this->send($request, $redirectLimit);
    }

    public function delete(string|URL $url): false|Response
    {
        return $this->send(new Request($url, 'DELETE'));
    }

    public function trace(string|URL $url): false|Response
    {
        return $this->send(new Request($url, 'TRACE'));
    }

    /**
     * Send a request to the desination host.
     *
     * @param Request $request       The request to send
     * @param int     $redirectLimit The number of allowed redirects.  To disable
     *                               automated redirects on this request, set to FALSE.
     */
    public function send(Request $request, int $redirectLimit = 10): ?Response
    {
        $this->SetHeader('Connection', 'close');
        if (count($this->headers) > 0) {
            foreach ($this->headers as $header => $value) {
                $request->setHeader($header, $value);
            }
        }
        if ($this->localCert) {
            $request->setLocalCertificate($this->localCert, $this->certPassphrase);
        }
        if ($this->auth) {
            $request->authorisation($this->auth);
        }
        if ($this->username) {
            $request->authorise($this->username, $this->password);
        }
        $this->applyCookies($request);
        $sck_fd = @stream_socket_client($request->getHost(), $errno, $errstr, $this->connectionTimeout, STREAM_CLIENT_CONNECT, $request->context);
        if ($sck_fd) {
            $http_request = $request->toString($this->encryptionKey, $this->encryptionCipher);
            fputs($sck_fd, $http_request, strlen($http_request));
            $response = new Response();
            $bufferSize = $this->bufferSize;
            while (($buf = fread($sck_fd, $bufferSize)) !== false) {
                $response->read($buf);
                /*
                 * Dynamic buffer resize.  This fixes a problem when the bytes remaining is less than the buffer size,
                 * but the buffer has already been read.
                 *
                 * If we have a content-length and the buffer len is greater or equal to it, dump out as we have all our
                 * content.
                 */
                if ($response->bytesRemaining > 0 && $response->bytesRemaining < $bufferSize) {
                    $bufferSize = $response->bytesRemaining;
                }
                // If the socket is now EOF then break out
                if (feof($sck_fd)) {
                    break;
                }
            }
            fclose($sck_fd);
            if (!$response->status > 0) {
                throw new \Exception('Host returned no data', 503);
            }
            if ($this->autoRedirect && (301 == $response->status || 302 == $response->status)) {
                if (in_array($request->method, $this->redirectMethods)) {
                    if ($redirectLimit <= 0) {
                        throw new Exception\TooManyRedirects();
                    }
                    if (!preg_match('/^http[s]?\:\/\//', $response['location'])) {
                        if ('/' == substr($response['location'], 0, 1)) {
                            $request->url()['path'] = $response['location'];
                        } else {
                            $request->url()['path'] .= (('/' == substr($request->url()['path'], -1, 1)) ? null : '/').$response['location'];
                        }
                    } else {
                        $request->url(new URL($response['location']));
                    }

                    return self::send($request, --$redirectLimit);
                }

                throw new RedirectNotAllowed($response->status);
            } elseif (303 == $response->status) {
                $request->method = 'GET';

                return $this->get($response['location'], --$redirectLimit);
            }
        } else {
            if (0 === $errno && !$errstr) {
                $errstr = 'Possible error initialising socket';
            }

            throw new \Exception('Error #'.$errno.': '.$errstr);
        }
        if ($cookie = $response->getHeader('set-cookie')) {
            $this->setCookie($cookie);
        }
        if ($response->hasHeader(strtolower(Client::$encryptionHeader))) {
            $response->decrypt($this->encryptionKey, $this->encryptionCipher);
        }

        return $response;
    }

    /**
     * @return array<mixed>
     */
    public function getCookies(): array|string
    {
        $list = [];
        foreach ($this->cookies as $cookie) {
            $list[] = $cookie['name'].'='.$cookie['value'];
        }
        if (count($list) > 1) {
            return $list;
        }

        return array_pop($list);
    }

    /**
     * @return null|array<string>|string
     */
    public function getCookie(string $name): null|array|string
    {
        $list = [];
        foreach ($this->cookies as $cookie) {
            if ($cookie['name'] === $name) {
                $list[] = $cookie['name'].'='.$cookie['value'];
            }
        }
        if (0 === count($list)) {
            return null;
        }
        if (count($list) > 1) {
            return $list;
        }

        return array_pop($list);
    }

    public function hasCookie(string $name): bool
    {
        return null !== $this->getCookie($name);
    }

    public function deleteCookie(string $name): void
    {
        foreach ($this->cookies as $index => $cookie) {
            if ($cookie['name'] === $name) {
                unset($this->cookies[$index]);
            }
        }
    }

    /**
     * @param array<string>|string $cookie
     *
     * @return array<mixed>
     */
    public function setCookie(array|string $cookie): array
    {
        if (is_array($cookie)) {
            $cookies = [];
            foreach ($cookie as $c) {
                $cookies[] = $this->setCookie($c);
            }

            return $cookies;
        }
        $parts = explode(';', $cookie);
        list($name, $value) = explode('=', array_shift($parts), 2);
        $data = [
            'name' => $name,
            'value' => $value,
            'domain' => null,
            'path' => '/',
            'expires' => null,
            'secure' => false,
            'httponly' => false,
        ];
        foreach ($parts as $part) {
            if (false !== strpos($part, '=')) {
                list($arg_k, $arg_v) = explode('=', $part);
                $arg_k = strtolower(trim($arg_k));
                if ('expires' === $arg_k) {
                    $arg_v = new Date($arg_v);
                } elseif ('max-age' === $arg_k) {
                    $arg_v = (new Date())->add('PT'.$arg_v.'S');
                }
                $data[$arg_k] = $arg_v;
            } else {
                $data[strtolower(trim($part))] = true;
            }
        }
        $key = md5($name.ake($data, 'domain').ake($data, 'path'));

        return $this->cookies[$key] = $data;
    }

    public function cacheCookie(Cache $cache, bool $cacheAll = false): bool
    {
        if (!count($this->cookies) > 0) {
            return false;
        }
        if (true === $cacheAll) {
            $cache->set('hazaar-http-client-cookies', $this->cookies);
        } else {
            $cachable = [];
            foreach ($this->cookies as $key => $cookie) {
                if (($cookie['expires'] instanceof Date
                    && $cookie['expires']->getTimestamp() > time())
                    || null === $cookie['expires']) {
                    $cachable[$key] = $cookie;
                }
            }
            $cache->set('hazaar-http-client-cookies', $cachable);
        }

        return true;
    }

    public function uncacheCookie(Cache $cache): bool
    {
        if ($this->cookies = $cache->get('hazaar-http-client-cookies')) {
            return true;
        }
        $this->cookies = [];

        return false;
    }

    public function deleteCookies(): void
    {
        $this->cookies = [];
    }

    public function disableRedirect(): void
    {
        $this->autoRedirect = false;
    }

    public function enableEncryption(?string $key = null, ?string $cipher = null): void
    {
        if (null === $key) {
            if (null === Client::$encryptionDefaultKey) {
                if (!($keyfile = Loader::getFilePath(FILE_PATH_CONFIG, '.key'))) {
                    throw new \Exception('Unable to encrypt.  No key provided and no default keyfile!');
                }
                Client::$encryptionDefaultKey = trim(file_get_contents($keyfile));
            }
            $key = Client::$encryptionDefaultKey;
        }
        $this->encryptionKey = $key;
        if (null === $cipher) {
            $cipher = Client::$encryptionDefaultCipher;
        }
        $this->encryptionCipher = $cipher;
    }

    private function applyCookies(Request $request): Request
    {
        if (!(count($this->cookies) > 0)) {
            return $request;
        }
        $url = $request->url();
        $path = explode('/', trim($url->path(), '/'));
        $cookies = [];
        foreach ($this->cookies as $cookie_key => $cookieData) {
            if ($expires = ake($cookieData, 'expires', null, true)) {
                if ($expires->getTimestamp() < time()) {
                    unset($this->cookies[$cookie_key]);

                    continue;
                }
            }
            if ($domain = ake($cookieData, 'domain')) {
                $domain = array_reverse(explode('.', $domain));
                if (!(array_slice(array_reverse(explode('.', $url->host())), 0, count($domain)) === $domain)) {
                    continue;
                }
            }
            if ($cookiePath = trim($cookieData['path'], '/')) {
                $cookiePath = explode('/', $cookiePath);
            } else {
                $cookiePath = [];
            }
            if (!(array_slice($path, 0, count($cookiePath)) === $cookiePath)) {
                continue;
            }
            if (true === $cookieData['secure'] && 'https' !== $url->scheme()) {
                continue;
            }
            $cookies[] = $cookieData['name'].'='.ake($cookieData, 'value');
        }
        $request->setHeader('Cookie', implode(';', $cookies), false);

        return $request;
    }
}
