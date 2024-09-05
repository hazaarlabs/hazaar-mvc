<?php

declare(strict_types=1);

namespace Hazaar\Controller;

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

    /**
     * Write the response to the output buffer.
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
                throw new Exception\HeadersSent();
            }
            $this->writeHeaders(strlen($content));
        }
        echo $content;
        flush();
    }

    public function __sleep()
    {
        return ['content', 'contentType', 'headers', 'statusCode'];
    }

    /**
     * Add Header Directive.
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
        if (!is_array($headers)) {
            return false;
        }
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value, $overwrite);
        }

        return true;
    }

    public function removeHeader(string $key): void
    {
        if (array_key_exists($key, $this->headers)) {
            unset($this->headers[$key]);
        }
    }

    /**
     * @return array<string, array<string>|string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return null|array<string>|string
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

    public function clearHeaders(): void
    {
        $this->headers = [];
    }

    public function modified(): bool
    {
        return 304 !== $this->statusCode;
    }

    /**
     * Quick method to set the content type.
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
     * Quick method to get the content type.
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContent(): string
    {
        return '';
    }

    public function setContent(mixed $content): void {}

    public function getContentLength(): int
    {
        return 0;
    }

    public function hasContent(): bool
    {
        return false;
    }

    public function addContent(mixed $content): void {}

    public function setStatus(int $status): void
    {
        $this->statusCode = $status;
    }

    public function getStatus(): int
    {
        return $this->statusCode;
    }

    public function getStatusMessage(): string
    {
        return http_response_text($this->statusCode);
    }

    public function ignoreHeaders(): void
    {
        $this->headersSet = true;
    }

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
