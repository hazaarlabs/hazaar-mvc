<?php

declare(strict_types=1);

namespace Hazaar\Controller;

use Hazaar\Controller\Exception\HeadersSent;
use Hazaar\HTTP\Client;

class Response implements Interfaces\Response
{
    public static ?string $encryptionKey = null;

    // Use text/html as the default type as it is the most widely accepted.
    /**
     * @var array<string, array<string>|string>
     */
    protected array $headers = [];
    protected bool $headersSet = false;
    protected string $contentType = 'text/plain';
    protected int $statusCode = 0;

    public function __construct(string $type = 'text/html', int $status = 501)
    {
        $this->contentType = $type;
        $this->statusCode = $status;
    }

    public function __sleep()
    {
        return ['content', 'contentType', 'headers', 'statusCode'];
    }

    /**
     * Writes the output content to the response.
     *
     * This method performs the following steps:
     * 1. Initializes the content to an empty string.
     * 2. Checks if the content has been modified and retrieves it if so.
     * 3. If an encryption key is set, encrypts the content using the default encryption cipher and sets the appropriate header.
     * 4. If the script is not running in CLI mode and headers have not been set, writes the headers.
     * 5. Outputs the content and flushes the output buffer.
     *
     * @throws HeadersSent if headers have already been sent
     */
    public function writeOutput(): void
    {
        $content = '';
        if ($this->modified()) {
            $content = $this->getContent();
        }
        if (null !== Response::$encryptionKey) {
            $encryptionCipher = Client::$encryptionDefaultCipher;
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($encryptionCipher));
            $content = base64_encode(openssl_encrypt($content, $encryptionCipher, Response::$encryptionKey, OPENSSL_RAW_DATA, $iv));
            $this->setHeader(Client::$encryptionHeader, base64_encode($iv));
        }
        if ('cli' !== php_sapi_name() && true !== $this->headersSet) {
            if (headers_sent()) {
                throw new HeadersSent();
            }
            $this->writeHeaders(strlen($content));
        }
        echo $content;
        flush();
    }

    /**
     * Sets a header for the response.
     *
     * This method allows setting a header key-value pair for the response. If the header key is 'content-length',
     * the method will return false and will not set the header. Otherwise, it will set the header based on the
     * provided parameters.
     *
     * @param string $key       the header key
     * @param string $value     the header value
     * @param bool   $overwrite Optional. Whether to overwrite the existing header value if the key already exists.
     *                          Default is true.
     *
     * @return bool returns true if the header was set successfully, false if the header key is 'content-length'
     */
    public function setHeader(string $key, string $value, bool $overwrite = true): bool
    {
        if ('content-length' === strtolower($key)) {
            return false;
        }
        if ($overwrite) {
            $this->headers[$key] = $value;
        } else {
            if (!(array_key_exists($key, $this->headers) && is_array($this->headers[$key]))) {
                $this->headers[$key] = isset($this->headers[$key]) ? [$this->headers[$key]] : [];
            }
            $this->headers[$key][] = $value;
        }

        return true;
    }

    /**
     * Add multiple headers.
     *
     * @param array<array<string>|string> $headers
     */
    public function setHeaders(array $headers, bool $overwrite = true): bool
    {
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value, $overwrite);
        }

        return true;
    }

    /**
     * Removes a header from the response.
     *
     * This method checks if a header with the specified key exists in the headers array.
     * If it does, the header is removed.
     *
     * @param string $key the key of the header to be removed
     */
    public function removeHeader(string $key): void
    {
        if (array_key_exists($key, $this->headers)) {
            unset($this->headers[$key]);
        }
    }

    /**
     * Retrieves the headers associated with the response.
     *
     * @return array<string, array<string>|string> an array of headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Retrieves the header value associated with the specified key.
     *
     * @param string $key the key of the header to retrieve
     *
     * @return null|array<string>|string the value of the header if found, or null if not found
     */
    public function &getHeader(string $key): null|array|string
    {
        if ($header = ake($this->headers, $key)) {
            if (is_array($header)) {
                return $header[0];
            }

            return $this->headers[$key];
        }
        $null = null;

        return $null;
    }

    /**
     * Clears all the headers from the response.
     *
     * This method resets the headers array, effectively removing any headers
     * that have been set previously. It is useful when you need to start with
     * a clean slate for headers before setting new ones.
     */
    public function clearHeaders(): void
    {
        $this->headers = [];
    }

    /**
     * Checks if the response has been modified.
     *
     * This method determines if the response status code indicates that the content
     * has been modified. A status code of 304 means "Not Modified", so this method
     * returns true if the status code is anything other than 304.
     *
     * @return bool true if the response has been modified, false otherwise
     */
    public function modified(): bool
    {
        return 304 !== $this->statusCode;
    }

    /**
     * Sets the content type for the response.
     *
     * If no content type is provided, it attempts to detect the MIME type
     * of the current content using the `finfo` extension.
     *
     * @param null|string $type The MIME type to set. If null, the MIME type will be auto-detected.
     */
    public function setContentType(?string $type = null): void
    {
        if (!$type) {
            // Try and detect the mimetype of the data we have.
            $finfo = new \finfo(FILEINFO_MIME);
            $type = $finfo->buffer($this->getContent());
        }
        $this->contentType = $type;
    }

    /**
     * Retrieves the content type of the response.
     *
     * @return string the content type of the response
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Retrieves the content of the response.
     *
     * @return string an empty string as the content
     */
    public function getContent(): string
    {
        return '';
    }

    /**
     * Sets the content for the response.
     *
     * @param mixed $content the content to be set for the response
     */
    public function setContent(mixed $content): void {}

    /**
     * Retrieves the content length.
     *
     * @return int the length of the content, which is currently always 0
     */
    public function getContentLength(): int
    {
        return 0;
    }

    /**
     * Checks if the response has content.
     *
     * @return bool returns false indicating that the response does not have content
     */
    public function hasContent(): bool
    {
        return false;
    }

    /**
     * Adds content to the response.
     *
     * @param mixed $content the content to be added to the response
     */
    public function addContent(mixed $content): void {}

    /**
     * Sets the HTTP status code for the response.
     *
     * @param int $status the HTTP status code to set
     */
    public function setStatus(int $status): void
    {
        $this->statusCode = $status;
    }

    /**
     * Retrieves the current status code of the response.
     *
     * @return int the status code of the response
     */
    public function getStatus(): int
    {
        return $this->statusCode;
    }

    /**
     * Retrieves the status message corresponding to the current HTTP status code.
     *
     * This method uses the `http_response_text` function to convert the status code
     * stored in the `$statusCode` property to its associated textual representation.
     *
     * @return string the status message corresponding to the current HTTP status code
     */
    public function getStatusMessage(): string
    {
        return http_response_text($this->statusCode);
    }

    /**
     * Marks the headers as set, effectively ignoring any further header modifications.
     *
     * This method sets the internal flag `$headersSet` to `true`, which indicates that
     * headers have already been sent or should be ignored. This can be useful in scenarios
     * where you want to prevent any additional headers from being added to the response.
     */
    public function ignoreHeaders(): void
    {
        $this->headersSet = true;
    }

    /**
     * Sets the HTTP response headers.
     *
     * This method sets the HTTP status code, content type, content length, and any additional headers
     * specified in the `$this->headers` array. If the content length is not provided, it will be
     * determined by the `getContentLength` method.
     *
     * @param null|int $content_length The length of the content. If null, it will be determined automatically.
     */
    private function writeHeaders(?int $content_length = null): void
    {
        http_response_code($this->statusCode);
        if ($contentType = $this->getContentType()) {
            header('Content-Type: '.$contentType);
        }
        if (null === $content_length) {
            $content_length = $this->getContentLength();
        }
        header('Content-Length: '.$content_length);
        foreach ($this->headers as $name => $header) {
            if (is_array($header)) {
                foreach ($header as $value) {
                    header($name.': '.$value, false);
                }
            } else {
                header($name.': '.$header);
            }
        }
        $this->headersSet = true;
    }
}
