<?php

declare(strict_types=1);

namespace Hazaar\HTTP;

use Hazaar\Auth\Adapter;
use Hazaar\HTTP\Exception\CertificateNotFound;
use Hazaar\HTTP\Exception\HostNotFound;
use Hazaar\XML\Element;

/**
 * HTTP Request class.
 *
 * This class is used to create and manage HTTP requests.  It is used by the `Client` class to create and send requests to
 * remote servers.  The `Request` class is used to create the request and the `Client` class is used to send the request and
 * receive the response.
 *
 * The `Request` class is used to create the request and the `Client` class is used to send the request and receive the response.
 *
 * @implements \ArrayAccess<string,mixed>
 */
class Request implements \ArrayAccess
{
    public string $method = 'GET';

    /**
     * @var resource
     */
    public mixed $context;
    public string $rawURL;

    /**
     * @var array<string,array<string>|string>
     */
    private $headers = [
        'User-Agent' => 'hazaarmvc-php/'.HAZAAR_VERSION.' (PHP)',
        'Content-Type' => 'text/html',
        'Connection' => 'close',
    ];
    private URL $url;
    private string $fsock_host;
    private mixed $body = null;
    private bool $multipart = false;
    private bool $dontEncodeURL = false;
    private int $jsonEncodeFlags = 0;
    private int $jsonEncodeDepth = 512;

    /**
     * @var array<mixed>
     */
    private array $data = [];

    /**
     * HTTP request constructor.
     *
     * @param string   $url            The url of the resource that will be requested
     * @param string   $method         The request method to use.  Typically GET, POST, etc
     * @param string   $content_type   optionally set the content type header
     * @param resource $custom_context Optionally use a custom context.  Allows to define private SSL certificates.
     */
    public function __construct(
        null|string|URL $url = null,
        string $method = 'GET',
        ?string $content_type = null,
        mixed $custom_context = null
    ) {
        if ($method) {
            $this->method = $method;
        }
        if (!$content_type) {
            if ('POST' == $this->method) {
                $content_type = 'application/x-www-form-urlencoded';
            } else {
                $content_type = 'text/html';
            }
        }
        $this->setHeader('Content-Type', $content_type);
        $this->url($url);
        $this->context = null === $custom_context ? stream_context_create() : $custom_context;
    }

    /**
     * Set the url of the resource that is being requested.
     */
    public function url(null|string|URL $url = null): URL
    {
        if (null === $url) {
            return $this->url;
        }
        $this->rawURL = $url;
        $this->url = ($url instanceof URL) ? $url : new URL($url);
        /*
         * !!NOTE!!
         * This is a hack to prevent bad hosts from throwing a FATAL error when trying to connect to them.
         * I don't know why a FATAL error is thrown just because host we're connecting to is not found, but this
         * will check that the hostname exists first before trying to continue.
         */
        if (false == ip2long(gethostbyname($this->url->host()))) {
            throw new HostNotFound($this->url->host());
        }
        // If the protocol ends in s, we're going to assume it is meant for SSL
        if ($this->url->isSecure()) {
            $this->fsock_host = 'ssl://'.$this->url->host().':'.$this->url->port();
        } else {
            $this->fsock_host = 'tcp://'.$this->url->host().':'.$this->url->port();
        }
        $this->setHeader('Host', $this->url->host());

        return $this->url;
    }

    /**
     * Sets the Content-Type header for the request.
     */
    public function setContentType(string $content_type): void
    {
        $this->setHeader('Content-Type', $content_type);
    }

    /**
     * Returns the current Content-Type header for the request.
     */
    public function getContentType(): string
    {
        return $this->getHeader('Content-Type');
    }

    /**
     * Enable multipart mime request body optionally using the specified boundary and content type.
     *
     * @param string $content_type Optional request content type to use.  Defaults to multipart/form-data.
     * @param string $boundary     Optional boundary identifier. Defaults to HazaarMVCMultipartBoundary_{uniqid}
     *
     * @return bool True if multipart was enabled.  False if it was already enabled.
     */
    public function enableMultipart(?string $content_type = null, ?string $boundary = null): bool
    {
        if (true === $this->multipart) {
            return false;
        }
        $this->multipart = true;
        $this->body = (null === $this->body) ? [] : [$this->getContentType(), $this->body];
        if (!$boundary) {
            $boundary = 'HazaarMVCMultipartBoundary_'.uniqid();
        }
        if (!$content_type) {
            $content_type = 'multipart/form-data';
        }
        $this->setContentType($content_type.'; boundary="'.$boundary.'"');

        return true;
    }

    /**
     * Returns a boolean indicating if the request is a multipart request.
     */
    public function isMultipart(): bool
    {
        return $this->multipart;
    }

    /**
     * Return the current multipart boundary name.
     */
    public function getMultipartBoundary(): false|string
    {
        if (true !== $this->multipart) {
            return false;
        }
        $content_type = $this->getHeader('Content-Type');
        if (!preg_match('/^multipart\/.*boundary\s*=\s*"(.*)"/', $content_type, $matches)) {
            return false;
        }

        return $matches[1];
    }

    /**
     * Add a multipart chunk to the request.
     *
     * @param mixed                     $data         the data to add to the request
     * @param string                    $content_type the content type of the added data
     * @param null|array<string,string> $headers
     */
    public function addMultipart(mixed $data, ?string $content_type = null, ?array $headers = null): void
    {
        if (!$content_type) {
            if (is_array($data)) {
                $content_type = 'application/json';
            } else {
                $content_type = 'text/text';
            }
        }
        if (true !== $this->multipart) {
            $this->enableMultipart();
        }
        if (!is_array($headers)) {
            $headers = [];
        }
        $headers['Content-Type'] = $content_type;
        $part = [$headers, $data];
        $this->body[] = $part;
    }

    /**
     * Returns the name of the host that will be sent this request.
     */
    public function getHost(): string
    {
        return $this->fsock_host;
    }

    /**
     * Set the request body.
     *
     * If multipart is enabled, then the body will be added as a new chunk.
     */
    public function setBody(mixed $body, ?string $content_type = null): void
    {
        if ($body instanceof Element) {
            if (null === $content_type) {
                $content_type = 'application/xml';
            }
            $body = $body->toXML();
        }
        if (is_array($this->body)) {
            $this->body[] = [$content_type, $body];
        } else {
            if ($content_type) {
                $this->setContentType($content_type);
            }
            $this->body = $body;
        }
    }

    /**
     * Return the body of the request.
     *
     * If multipart is enabled, this will return an array containing request body and content type.
     *
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Returns all the headers currently set on the request.
     *
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Returns the value of the header specified by $key.
     *
     * @param string $key The name of the header to return
     */
    public function getHeader(string $key): string
    {
        return ake($this->headers, $key);
    }

    /**
     * Sets the value of a header.
     *
     * @param string $key            the name of the header to set
     * @param string $value          the value to set on the header
     * @param bool   $allow_multiple Whether multiple instances of the header are allowed.  Defaults to false, meaning if the
     *                               header exists, it will be updated.  Multiple headers are rare but the main one is 'Cookie'.
     */
    public function setHeader(string $key, string $value, bool $allow_multiple = false): void
    {
        if (true === $allow_multiple && array_key_exists($key, $this->headers)) {
            if (!is_array($this->headers[$key])) {
                $this->headers[$key] = [$this->headers[$key]];
            }
            $this->headers[$key][] = $value;
        } else {
            $this->headers[$key] = $value;
        }
    }

    /**
     * Output the request as a string.
     *
     * This is the method that renders the request as a HTTP/1.1 compliant request.
     *
     * @param string $encryption_key    optionally encrypt the request using this encryption key
     * @param string $encryption_cipher optionally specifiy the cipher used to encrypt the request
     */
    public function toString(?string $encryption_key = null, ?string $encryption_cipher = null): string
    {
        $url = clone $this->url;
        $body = '';
        // Convert any parameters into a HTTP POST query
        if ('GET' === $this->method && $this->count() > 0 && null === $encryption_key) {
            $url->setParams($this->toArray());
        } elseif (null !== $this->body) {
            if ($this->count() > 0) {
                $url->setParams($this->toArray());
            }
            if (true === $this->multipart) {
                $boundary = $this->getMultipartBoundary();
                foreach ($this->body as $part) {
                    $body .= "--{$boundary}\r\n";
                    if (!is_array($part[0])) {
                        $part[0] = ['Content-Type' => $part[0]];
                    }
                    if ($content_type = ake($part[0], 'Content-Type')) {
                        if (($pos = strpos($content_type, ';')) > 0) {
                            $content_type = substr($content_type, 0, $pos);
                        }

                        switch ($content_type) {
                            case 'text/json' :
                            case 'application/json' :
                                $data = json_encode($part[1], $this->jsonEncodeFlags, $this->jsonEncodeDepth);

                                break;

                            case 'text/html' :
                            case 'application/x-www-form-urlencoded':
                                $data = is_array($part[1]) ? http_build_query($part[1]) : $part[1];

                                break;

                            default:
                                $data = &$part[1];

                                break;
                        }
                    } else {
                        $data = $part[1];
                    }
                    foreach ($part[0] as $key => $value) {
                        $body .= $key.': '.$value."\r\n";
                    }
                    $body .= "\r\n{$data}\r\n";
                }
                $body .= "--{$boundary}--\r\n";
            } elseif (is_array($this->body) || $this->body instanceof \stdClass) {
                $this->setHeader('Content-Type', 'application/json');
                $body = json_encode($this->body, $this->jsonEncodeFlags, $this->jsonEncodeDepth);
            } else { // Otherwise use the raw content body
                $body = $this->body;
            }
        } elseif ($this->count() > 0) {
            $content_type = $this->getHeader('Content-Type');
            if (($pos = strpos($content_type, ';')) > 0) {
                $content_type = substr($content_type, 0, $pos);
            }

            switch ($content_type) {
                case 'text/json' :
                case 'application/json' :
                case 'application/javascript' :
                case 'application/x-javascript' :
                    $body = $this->toJSON($this->jsonEncodeFlags, $this->jsonEncodeDepth);

                    break;

                case 'text/html' :
                case 'application/x-www-form-urlencoded':
                default:
                    $body = http_build_query($this->toArray());

                    break;
            }
        }
        if (null !== $encryption_key) {
            if (null === $encryption_cipher) {
                $encryption_cipher = Client::$encryptionDefaultCipher;
            }
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($encryption_cipher));
            $body = base64_encode(openssl_encrypt($body, $encryption_cipher, $encryption_key, OPENSSL_RAW_DATA, $iv));
            $this->setHeader(Client::$encryptionHeader, base64_encode($iv));
        }
        // Always include a content-length header.  Fixes POST to IIS returning 411 (length required).
        $this->setHeader('Content-Length', (string) strlen($body));
        // Build the header section
        $access_url = ($this->dontEncodeURL ? $url->path() : implode('/', array_map('rawurlencode', explode('/', $url->path())))).$url->queryString();
        $http_request = "{$this->method} {$access_url} HTTP/1.1\r\n";
        foreach ($this->headers as $header => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }
            foreach ($value as $hdr) {
                $http_request .= $header.': '.$hdr."\r\n";
            }
        }
        $http_request .= "\r\n".$body;

        return $http_request;
    }

    /**
     * Add Basic HTTP authentication.
     *
     * Basic authentication should ONLY be used over HTTPS.
     *
     * Other methods are not yet supported, however Bearer is implemented using Request::authorization() method.
     *
     * @param string $username The username to send
     * @param string $password The password to send
     */
    public function authorise(string $username, string $password): void
    {
        $auth = 'Basic '.base64_encode($username.':'.$password);
        $this->setHeader('Authorization', $auth);
    }

    /**
     * Alias for 'authorisation' for the bad 'merican spells.
     */
    public function authorization(Adapter $user, string $type = 'Bearer'): bool
    {
        return $this->authorisation($user, $type);
    }

    /**
     * Use an auth adapter to set an Oauth token on the request.
     */
    public function authorisation(Adapter $user, ?string $type = null): bool
    {
        // if ($token = $user->getToken()) {
        //     if (!$type) {
        //         $type = $user->getTokenType();
        //     }
        //     $this->setHeader('Authorization', $type.' '.$token);

        //     return true;
        // }

        return false;
    }

    /**
     * Set a local PEM encoded certificate to use for SSL communication.
     */
    public function setLocalCertificate(string $local_cert, ?string $passphrase = null, ?string $local_pk = null): bool
    {
        if (!file_exists((string) $local_cert)) {
            throw new CertificateNotFound();
        }
        $result = stream_context_set_option($this->context, 'ssl', 'local_cert', $local_cert);
        if ($local_pk) {
            if (!file_exists((string) $local_pk)) {
                throw new \Exception('Local private key specified but the file does not exist!');
            }
            stream_context_set_option($this->context, 'ssl', 'local_pk', $local_pk);
        }
        if ($passphrase) {
            stream_context_set_option($this->context, 'ssl', 'passphrase', $passphrase);
        }

        return $result;
    }

    /**
     * Wrapper function to the internal PHP function stream_context_set_option() function.
     *
     * See http://php.net/manual/en/context.ssl.php documentation for all the available wrappers and options.
     *
     * @param array<string> $options Must be an associative array in the format $arr['wrapper']['option'] = $value;
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function setContextOption(array $options): bool
    {
        return stream_context_set_option($this->context, $options);
    }

    /**
     * When using SSL communications, this allows the use of self-signed certificates.
     */
    public function allowSelfSigned(bool $value = true): bool
    {
        return stream_context_set_option($this->context, 'ssl', 'allow_self_signed', $value);
    }

    /**
     * When using SSL communications, this sets whether peer certificate verification is used.
     */
    public function verifyPeer(bool $value = true): bool
    {
        return stream_context_set_option($this->context, 'ssl', 'verify_peer', $value);
    }

    /**
     * When using SSL communications, this sets whether peer name verification is used.
     */
    public function verifyPeerName(bool $value = true): bool
    {
        return stream_context_set_option($this->context, 'ssl', 'verify_peer_name', $value);
    }

    /**
     * Enable/Disable URL encoding.
     *
     * url encoding is enabled by default.  Internally this calls PHPs rawurlencode() function to encode URLs into HTTP safe
     * urls.  However there may occasionally be special circumstances where the encoding may need to be disabled.  Usually this
     * is becuase the encoding is already being done by the calling function/class.
     *
     * A prime example of this is Hazaar's SharePoint filesystem backend driver.  SharePoint is very finicky about the
     * format of the urls and wants some characters left alone (ie: brackets and quotes) as they make up the function/path
     * reference being accessed.  These functions/references will then have their contents only encoded and this is handled
     * by the driver itself so encoding again in the `Request` class will screw things up.
     *
     * @param bool $value TRUE enables encoding (the default).  FALSE will disable encoding.
     */
    public function setURLEncode(bool $value = true): void
    {
        $this->dontEncodeURL = !boolify($value);
    }

    /**
     * Set JSON encoding flags/depth used when request is encoded to JSON.
     *
     * Requests will automatically encode any data parameters to JSON encoded strings when generating the reqeust as a string.  If
     * there are any JSON encoding flags required, this function will apply those flags to all JSON encoding methods used when
     * rendering the request.  This includes requests sent with a mime content type of `application/json` as well as multipart
     * encoded requests.
     */
    public function setJSONEncodeFlags(int $flags, int $depth = 512): void
    {
        $this->jsonEncodeFlags = $flags;
        $this->jsonEncodeDepth = $depth;
    }

    public function toJSON(int $flags = 0, int $depth = 512): string
    {
        return json_encode($this->toArray(), $flags, $depth);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return ake($this->data, $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param array<string,string> $data
     */
    public function populate(array $data): void
    {
        $this->data = $data;
    }
}
