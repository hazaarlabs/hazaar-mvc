<?php

declare(strict_types=1);

namespace Hazaar\HTTP;

class Response
{
    // Status code of the response
    public ?int $status = null;
    // Status message of the response
    public string $name;
    // HTTP Version of the response
    public string $version;

    /**
     * The actual body of the response.
     *
     * @var array<string>|string
     */
    public array|string $body = '';
    // Public variables
    public int $contentLength = 0;
    public int $bytesRemaining = -1;

    /**
     * @var array<string,array<string>|string>
     */
    public array $headers = [];
    // Temporary buffer used while parsing responses
    private ?string $buffer = null;
    // Parsing input
    private bool $headersParsed = false;
    // Used for chunked data parsing
    private bool $chunked = false;
    // Length of the current chunk
    private int $chunkOffset = 0;

    /**
     * @param array<string,array<string>|string> $headers
     */
    public function __construct(?int $status = null, array $headers = [], string $version = 'HTTP/1.1')
    {
        if (null !== $status) {
            $this->setStatus($status);
        }
        $this->version = $version;
        $this->headers = $headers;
        if (count($this->headers) > 0) {
            $this->headersParsed = true;
        }
    }

    /**
     * Magic method to allow access to headers as properties.
     *
     * @param string $key The header key to get
     *
     * @return null|array<string>|string
     */
    public function __get(string $key): null|array|string
    {
        return $this->getHeader($key);
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
        $this->name = $this->getStatusMessage($this->status);
    }

    public function write(string $string): void
    {
        $this->body .= $string;
        $this->contentLength = strlen($this->body);
    }

    public function read(string $buffer): bool
    {
        $this->buffer .= $buffer;
        $param = null;
        if (!$this->headersParsed) {
            $offset = 0;
            while (($del = strpos($this->buffer, "\r\n", $offset)) !== false) {
                // If we get an empty line, that's the end of the headers
                if ($del == $offset) {
                    $this->headersParsed = true;
                    $offset += 2;

                    break;
                }
                $header = substr($this->buffer, $offset, $del - $offset);
                if (null === $this->status) {
                    if (!preg_match('/(HTTP\/[\d\.]+)\s+(\d+)\s+(.*)/', $header, $matches)) {
                        throw new \Exception('Got bad HTTP response: '.$header);
                    }
                    // Parse the response header so we can throw errors if needed
                    list($null, $this->version, $status, $this->name) = $matches;
                    $this->status = (int) $status;
                } elseif (false !== ($split = strpos($header, ':'))) {
                    $param = strtolower(substr($header, 0, $split));
                    $value = trim(substr($header, $split + ($param ? 1 : 0)));
                    if (array_key_exists($param, $this->headers)) {
                        if (!is_array($this->headers[$param])) {
                            $this->headers[$param] = [$this->headers[$param]];
                        }
                        $this->headers[$param][] = $value;
                    } else {
                        $this->headers[$param] = $value;
                    }

                    switch ($param) {
                        case 'content-length':
                            $this->contentLength = (int) $value;

                            break;

                        case 'transfer-encoding':
                            if ('chunked' == $value) {
                                $this->chunked = true;
                            }

                            break;
                    }
                } elseif (null !== $param) {
                    $this->headers[$param] .= ' '.trim($header);
                } else {
                    throw new \Exception('Got bad HTTP response header: '.$header);
                }
                $offset = $del + 2;
                // Set the offset to the end of the last delimiter
            }
            // Truncate the buffer to remove the crap we've already just processed
            if ($offset > 0) {
                $this->buffer = substr($this->buffer, $offset);
            }
        }
        // Now process the content.  We check if the headers are set first.
        if ($this->headersParsed) {
            // Check the length of our content, either chunked or normal
            if ($this->chunked) {
                while (strlen($this->buffer) > 0) {
                    // Get the current chunk length
                    if (($chunkLen_end = strpos($this->buffer, "\r\n", $this->chunkOffset + 1) + 2) === 2) {
                        break;
                    }
                    $chunkLen_string = substr($this->buffer, 0, $chunkLen_end - $this->chunkOffset - 2);
                    $chunkLen = hexdec($chunkLen_string) + 2;
                    // If we don't have the whole chunk, bomb out for now.  This expects that this read method will be
                    // called again later with more of the response body.  The +2 includes the CRLF chunk terminator.
                    if ((strlen($this->buffer) - $chunkLen_end) < $chunkLen) {
                        break;
                    }
                    if (0 == $chunkLen) {
                        $this->buffer = null;
                        if (!$this->contentLength) {
                            $this->contentLength = strlen($this->body);
                        }

                        return true;
                    }
                    // Get the current chunk
                    $chunk = substr($this->buffer, $chunkLen_end, $chunkLen - 2);
                    // TODO: This is where we could fire off a callback with the current data chunk;
                    // call_user_func($callback, $chunk);
                    // Append the current chunk onto the body
                    $this->body .= $chunk;
                    // Remove the processed chunk from the buffer
                    $this->buffer = substr($this->buffer, $chunkLen_end + $chunkLen);
                }
            } else {
                // If we have a content length, check how many bytes are left to retrieve
                if ($this->contentLength > 0) {
                    $len = strlen($this->buffer);
                    if ($len >= $this->contentLength) {
                        $encoding = (array_key_exists('content-encoding', $this->headers) ? strtolower($this->headers['content-encoding']) : null);

                        switch ($encoding) {
                            case 'gzip':
                                $this->body = gzdecode($this->buffer);

                                break;

                            default:
                                $this->body .= $this->buffer;

                                break;
                        }
                        $this->buffer = null;

                        return true;
                    }
                    $this->bytesRemaining = $this->contentLength - $len;
                // Otherwise just start filling the body with data
                } else {
                    $this->body .= $this->buffer;
                    $this->buffer = null;
                }
            }
        }

        return false;
        // Return false to indicate that we haven't received all the content body
    }

    public function hasHeader(string $key): bool
    {
        return array_key_exists(strtolower($key), $this->headers);
    }

    /**
     * Get a header from the response.
     *
     * @param string $header The header key to get
     *
     * @return null|array<string>|string
     */
    public function getHeader(string $header): null|array|string
    {
        $header = strtolower($header);
        if (array_key_exists($header, $this->headers)) {
            return $this->headers[$header];
        }

        return null;
    }

    public function setHeader(string $header, ?string $content = null): void
    {
        $header = strtolower($header);
        if (null === $content) {
            unset($this->headers[$header]);
        } else {
            $this->headers[$header] = $content;
        }
    }

    public function size(): int
    {
        return strlen($this->body);
    }

    public function toString(): string
    {
        $http_response = "{$this->version} {$this->status} {$this->name}\r\n";
        foreach ($this->headers as $header => $value) {
            $http_response .= $header.': '.$value."\r\n";
        }
        $content_len = strlen($this->body);
        if ($content_len > 0) {
            $http_response .= 'Content-Length: '.$content_len."\r\n";
        }
        $http_response .= "\r\n".$this->body;

        return $http_response;
    }

    public function getStatusMessage(?int $code = null): string
    {
        if (!$code) {
            $code = $this->status;
        }

        return http_response_text($code);
    }

    /**
     * Get the content type of the response.
     *
     * This method will return the content type of the response and optionally return any arguments that were
     *
     * @param array<string,string> $args The arguments of the content type header will be returned in this array
     */
    public function getContentType(array &$args = []): string
    {
        $header = str_replace('; ', ';', $this->getHeader('content-type'));
        if (($start = strpos($header, ';')) === false) {
            return $header;
        }
        $contentType = substr($header, 0, $start);
        $args = array_unflatten(substr($header, $start + 1), '=', ';');

        return $contentType;
    }

    public function body(bool $raw = false): mixed
    {
        if (true === $raw) {
            return $this->body;
        }
        $args = [];
        $contentType = $this->getContentType($args);
        if ('application/json' === $contentType) {
            return json_decode($this->body);
        }
        if ('multipart' === substr($contentType, 0, 9)) {
            if (!array_key_exists('boundary', $args)) {
                throw new \Exception('Received multipart content type with no boundary!');
            }
            $parts = explode('--'.$args['boundary'], trim($this->body));
            if (!('' === $parts[0] && '--' === $parts[count($parts) - 1])) {
                throw new \Exception('Invalid multipart response received!');
            }
            $this->body = [];
            for ($i = 1; $i < (count($parts) - 1); ++$i) {
                $offset = 2;
                $headers = [];
                while (($del = strpos($parts[$i], "\r\n", $offset)) !== false) {
                    // If we get an empty line, that's the end of the headers
                    if ($del == $offset) {
                        $offset += 2;

                        break;
                    }
                    $header = substr($parts[$i], $offset, $del - $offset);
                    if (false !== ($split = strpos($header, ':'))) {
                        $param = strtolower(substr($header, 0, $split));
                        $value = trim(substr($header, $split + ($param ? 1 : 0)));
                        $headers[$param] = $value;
                    }
                    $offset = $del + 2;
                }
                $this->body[] = ['headers' => $headers, 'body' => substr($parts[$i], $offset)];
            }
        }

        return $this->body;
    }

    public function decrypt(string $key, ?string $cipher = null): bool
    {
        if (!($iv = $this->getHeader(Client::$encryptionHeader))) {
            return false;
        }
        if (null === $cipher) {
            $cipher = Client::$encryptionDefaultCipher;
        }
        $iv = base64_decode($iv);
        $decryptedValue = openssl_decrypt(base64_decode($this->body), $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if (false === $decryptedValue) {
            throw new Exception\DecryptFailed(openssl_error_string());
        }
        $this->body = $decryptedValue;

        return true;
    }
}
