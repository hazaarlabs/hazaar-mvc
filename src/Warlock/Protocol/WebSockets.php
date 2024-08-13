<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Protocol;

abstract class WebSockets
{
    /**
     * @var array<string>
     */
    protected array $allowed_protocols = [];
    private string $magicGUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /**
     * @var array<string>
     */
    private array $headers = [];

    /**
     * @param array<string> $allowed_protocols
     */
    public function __construct(array $allowed_protocols = [])
    {
        if (!is_array($allowed_protocols)) {
            $allowed_protocols = explode(' ', $allowed_protocols);
        }
        $this->allowed_protocols = $allowed_protocols;
    }

    /**
     * @return array<string>
     */
    public function parseHeaders(string $request, string &$body = ''): array|false
    {
        $headers = [];
        list($header, $body) = explode("\r\n\r\n", $request, 2);
        $lines = explode("\n", $request);
        $lead = explode(' ', $lines[0], 3);
        if (!isset($lead[1])) {
            return false;
        }
        if (is_numeric($lead[1])) {
            $headers['code'] = (int) $lead[1];
            $headers['status'] = trim($lead[2]);
        } else {
            $headers[strtolower($lead[0])] = trim($lead[1]);
        }
        foreach ($lines as $line) {
            $parts = explode(': ', $line);
            // Silently ignore bad headers
            if (2 !== count($parts)) {
                continue;
            }
            $headers[strtolower($parts[0])] = trim($parts[1]);
        }

        return $headers;
    }

    /**
     * @param array<string> $extra_headers
     */
    protected function createHandshake(
        string $path,
        string $host,
        ?string $origin,
        string $key,
        ?array $extra_headers = null
    ): string {
        $headers = [
            'Host' => $host,
            'Connection' => 'Upgrade',
            'Upgrade' => 'WebSocket',
            'Sec-WebSocket-Key' => $key,
            'Sec-WebSocket-Version' => 13,
            'Sec-WebSocket-Protocol' => 'warlock',
        ];
        if ($origin) {
            $header['Origin'] = $origin;
        }
        if (is_array($extra_headers)) {
            $headers = array_merge($headers, $extra_headers);
        }
        $requestHeaders = 'GET '.$path." HTTP/1.1\r\n";
        foreach ($headers as $name => $value) {
            $requestHeaders .= $name.': '.$value."\r\n";
        }

        return $requestHeaders."\r\n";
    }

    /**
     * @param array<string> $headers
     * @param array<string> $responseHeaders
     * @param array<string> $results
     */
    protected function acceptHandshake(
        array $headers,
        array &$responseHeaders = [],
        ?string $key = null,
        array &$results = []
    ): bool|int {
        if (!is_array($headers)) {
            $headers = $this->parseHeaders($headers);
        }
        $this->headers = $headers;
        if (array_key_exists('get', $headers)) {
            if (!array_key_exists('host', $headers) || !($results['host'] = $this->checkHost($headers['host']))) {
                return 400;
            }
            if (false === ($results['url'] = $this->checkRequestURL($headers['get']))) {
                return 404;
            }
            if (array_key_exists('upgrade', $headers)) {
                // New WebSockets Handshake
                if (!array_key_exists('connection', $headers) || false === strpos(strtolower($headers['connection']), 'upgrade')) {
                    return 400;
                }
                if ('websocket' !== strtolower($headers['upgrade'])) {
                    return 400;
                }
                if (!array_key_exists('sec-websocket-key', $headers)) {
                    return 400;
                }
                if (!array_key_exists('sec-websocket-version', $headers) || 13 != (int) $headers['sec-websocket-version']) {
                    $responseHeaders['Sec-WebSocket-Version'] = 13;

                    return 426;
                }
                if (array_key_exists('origin', $headers) && !($results['origin'] = $this->checkOrigin($headers['origin']))) {
                    return 403;
                }
                if (!array_key_exists('sec-websocket-protocol', $headers) || !($results['protocols'] = $this->checkProtocol($headers['sec-websocket-protocol']))) {
                    return 400;
                }
                $responseHeaders = [
                    'Upgrade' => 'websocket',
                    'Connection' => 'Upgrade',
                    'Sec-WebSocket-Accept' => base64_encode(sha1($this->headers['sec-websocket-key'].$this->magicGUID, true)),
                    'Sec-WebSocket-Protocol' => implode(', ', $results['protocols']),
                ];

                return 101;
            }
        } elseif (array_key_exists('sec-websocket-accept', $headers)) {
            if (!array_key_exists('sec-websocket-accept', $headers) || base64_decode($headers['sec-websocket-accept']) != sha1($key.$this->magicGUID, true)) {
                return false;
            }
            if (!array_key_exists('sec-websocket-protocol', $headers) || 0 == count($this->checkProtocol($headers['sec-websocket-protocol']))) {
                return false;
            }

            return true;
        } else {
            return 405;
        }

        return false;
    }

    protected function frame(string $payload, ?string $type = null, bool $masked = true): false|string
    {
        if (!$type) {
            $type = 'text';
        }
        $frameHead = [];
        // Fix to correctly encode empty payloads
        if ($masked && 0 == strlen($payload)) {
            $payload = ' ';
        }
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'continuous':
                // first byte indicates FIN, Continuing-Frame (10000000):
                $frameHead[0] = 0x80;

                break;

            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 0x81;

                break;

            case 'binary':
                // first byte indicates FIN, Binary-Frame (10000010):
                $frameHead[0] = 0x82;

                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 0x88;

                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 0x89;

                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 0x8A;

                break;
        }
        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = (true === $masked) ? 255 : 127;
            for ($i = 0; $i < 8; ++$i) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0 (close connection if frame too big)
            if ($frameHead[2] > 127) {
                return false;
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = (true === $masked) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = (true === $masked) ? $payloadLength + 128 : $payloadLength;
        }
        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        $mask = [];
        if (true === $masked) {
            // generate a random mask:
            for ($i = 0; $i < 4; ++$i) {
                $mask[$i] = chr(rand(0, 255));
            }
            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);
        // append payload to frame:
        for ($i = 0; $i < $payloadLength; ++$i) {
            $frame .= (true === $masked) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    protected function getFrame(string &$buffer, ?string &$payload = null): bool|int
    {
        if (!$buffer) {
            return false;
        }
        if (strlen($buffer) < 2) {
            return -1;
        }
        $headers = $this->getFrameHeaders($buffer);
        $offset = 2;
        if (ord($headers['hasmask']) > 0) {
            $offset += 4;
        }
        if ($headers['length'] > 65535) {
            $offset += 8;
        } elseif ($headers['length'] > 125) {
            $offset += 2;
        }
        if ($headers['length'] > 0) {
            $payload = substr($buffer, $offset, $headers['length']);
            if ($headers['length'] > strlen($payload)) {
                return true;
            }
        }
        // Truncate the buffer so that we leave whatever is left over
        $buffer = substr($buffer, $offset + $headers['length']);
        if ($payload && ord($headers['hasmask']) > 0 && !($payload = $this->applyMask($headers, $payload))) {
            return false;
        }
        if ($headers['fin']) {
            return $headers['opcode'];
        }

        return false;
    }

    protected function checkHost(string $host): bool
    {
        return $host ? true : false;
    }

    /**
     * @return array<string,int|string>|bool
     */
    protected function checkRequestURL(string $path): array|bool
    {
        return $path ? true : false;
    }

    protected function checkOrigin(string $origin): bool
    {
        return $origin ? true : false;
    }

    /**
     * @param array<string>|string $protocols
     *
     * @return array<string>|false
     */
    protected function checkProtocol(array|string $protocols): array|false
    {
        $allowed = [];
        if (!is_array($protocols)) {
            $protocols = explode(',', $protocols);
        }
        foreach ($protocols as $proto) {
            $proto = strtolower(trim($proto));
            if (in_array($proto, $this->allowed_protocols)) {
                $allowed[] = $proto;
            }
        }
        if (count($allowed) > 0) {
            return $allowed;
        }

        return false;
    }

    /**
     * @return array<string>
     */
    protected function hexString(string $string): array
    {
        $bytes = [];
        $len = strlen($string);
        for ($i = 0; $i < $len; ++$i) {
            $bytes[] = str_pad(strtoupper(dechex(ord($string[$i]))), 2, '0', STR_PAD_LEFT);
        }

        return $bytes;
    }

    /**
     * @return array<int|string>
     */
    private function getFrameHeaders(string $frame): array
    {
        $header = [
            'fin' => $frame[0] & chr(128),
            'rsv1' => $frame[0] & chr(64),
            'rsv2' => $frame[0] & chr(32),
            'rsv3' => $frame[0] & chr(16),
            'opcode' => ord($frame[0]) & 15,
            'hasmask' => $frame[1] & chr(128),
            'length' => 0,
            'mask' => '',
        ];
        $header['length'] = (ord($frame[1]) >= 128) ? ord($frame[1]) - 128 : ord($frame[1]);
        if (126 == $header['length']) {
            if ($header['hasmask']) {
                $header['mask'] = $frame[4].$frame[5].$frame[6].$frame[7];
            }
            $header['length'] = ord($frame[2]) * 256 + ord($frame[3]);
        } elseif (127 == $header['length']) {
            if ($header['hasmask']) {
                $header['mask'] = $frame[10].$frame[11].$frame[12].$frame[13];
            }
            $header['length'] = ord($frame[2]) * 65536 * 65536 * 65536 * 256 + ord($frame[3]) * 65536 * 65536 * 65536 +
                                ord($frame[4]) * 65536 * 65536 * 256 + ord($frame[5]) * 65536 * 65536 + ord($frame[6]) * 65536 * 256 +
                                ord($frame[7]) * 65536 + ord($frame[8]) * 256 + ord($frame[9]);
        } elseif (ord($header['hasmask']) > 0) {
            $header['mask'] = $frame[2].$frame[3].$frame[4].$frame[5];
        }

        return $header;
    }

    /**
     * @param array<string> $headers
     */
    private function applyMask(array $headers, string $payload): false|string
    {
        $effectiveMask = '';
        if (ord($headers['hasmask']) > 0) {
            $mask = $headers['mask'];
        } else {
            return $payload;
        }
        if (0 == strlen($mask)) {
            return false;
        }
        while (strlen($effectiveMask) < strlen($payload)) {
            $effectiveMask .= $mask;
        }
        while (strlen($effectiveMask) > strlen($payload)) {
            $effectiveMask = substr($effectiveMask, 0, -1);
        }

        return $effectiveMask ^ $payload;
    }
}
