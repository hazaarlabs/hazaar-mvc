<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Connection;

use Hazaar\Warlock\Interface\Connection;
use Hazaar\Warlock\Protocol;
use Hazaar\Warlock\Protocol\WebSockets;

final class Socket extends WebSockets implements Connection
{
    public int $bytesReceived = 0;
    public int $socketLastError;
    protected string $id;
    protected string $key;
    protected false|\Socket $socket = false;
    protected bool $connected = false;
    protected Protocol $protocol;

    // WebSocket Buffers
    protected ?string $frameBuffer = null;
    protected ?string $payloadBuffer = null;
    protected bool $closing = false;

    public function __construct(Protocol $protocol, ?string $guid = null)
    {
        if (!extension_loaded('sockets')) {
            throw new \Exception('The sockets extension is not loaded.');
        }
        parent::__construct(['warlock']);
        $this->protocol = $protocol;
        $this->id = (null === $guid ? guid() : $guid);
        $this->key = uniqid();
    }

    final public function __destruct()
    {
        $this->disconnect();
    }

    public function connect(string $applicationName, string $host, int $port, ?array $extra_headers = null): bool
    {
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (false === $this->socket) {
            throw new \Exception('Unable to create TCP socket!');
        }
        if (!($this->connected = @socket_connect($this->socket, $host, $port))) {
            $this->socketLastError = socket_last_error($this->socket);
            error_clear_last();
            socket_close($this->socket);

            return $this->socket = false;
        }
        $headers = [
            'X-WARLOCK-PHP' => 'true',
            'X-WARLOCK-USER' => base64_encode(get_current_user()),
        ];
        if (is_array($extra_headers)) {
            $headers = array_merge($headers, $extra_headers);
        }

        /**
         * Initiate a WebSockets connection.
         */
        $handshake = $this->createHandshake('/'.$applicationName.'/warlock?CID='.$this->id, $host, null, $this->key, $headers);
        @socket_write($this->socket, $handshake, strlen($handshake));

        /**
         * Wait for the response header.
         */
        $read = [$this->socket];
        $write = $except = null;
        $sockets = socket_select($read, $write, $except, 3000);
        if (0 == $sockets) {
            return false;
        }
        socket_recv($this->socket, $buf, 65536, 0);
        $response = $this->parseHeaders($buf);
        if (101 != $response['code']) {
            throw new \Exception('Walock server returned status: '.$response['code'].' '.$response['status']);
        }
        $responseHeaders = [];
        if (!$this->acceptHandshake($response, $responseHeaders, $this->key)) {
            throw new \Exception('Warlock server denied our connection attempt!');
        }

        return true;
    }

    public function getLastSocketError(bool $as_string = false): string
    {
        return $as_string ? socket_strerror($this->socketLastError) : $this->socketLastError;
    }

    public function disconnect(): bool
    {
        $this->frameBuffer = '';
        if ($this->socket) {
            if (false === $this->closing) {
                $this->closing = true;
                $frame = $this->frame('', 'close');
                @socket_write($this->socket, $frame, strlen($frame));
                $this->recv($payload);
            }
            socket_close($this->socket);
            $this->socket = false;

            return true;
        }

        return false;
    }

    public function connected(): bool
    {
        return false !== $this->socket;
    }

    public function send(string $command, mixed $payload = null): bool
    {
        if (!$this->socket) {
            return false;
        }
        if (!($packet = $this->protocol->encode($command, $payload))) {
            return false;
        }
        $frame = $this->frame($packet, 'text');
        $len = strlen($frame);
        $attempts = 0;
        $total_sent = 0;
        while ($frame) {
            ++$attempts;
            $bytes_sent = @socket_write($this->socket, $frame, $len);
            if (-1 === $bytes_sent || false === $bytes_sent) {
                throw new \Exception('An error occured while sending to the socket');
            }
            $total_sent += $bytes_sent;
            if ($total_sent === $len) { // If all the bytes sent then don't waste time processing the leftover frame
                break;
            }
            if ($attempts >= 100) {
                throw new \Exception('Unable to write to socket.  Socket appears to be stuck.');
            }
            $frame = substr($frame, $bytes_sent);
        }

        return true;
    }

    public function recv(mixed &$payload = null, int $tv_sec = 3, int $tv_usec = 0): null|bool|string
    {
        // Process any frames sitting in the local frame buffer first.
        while ($frame = $this->processFrame()) {
            if (true === $frame) {
                break;
            }

            return $this->protocol->decode($frame, $payload);
        }
        if (!$this->socket) {
            exit(4);
        }
        $socketOption = socket_get_option($this->socket, SOL_SOCKET, SO_ERROR);
        if (is_int($socketOption) && $socketOption > 0) {
            exit(4);
        }
        $read = [
            $this->socket,
        ];
        $write = $except = null;
        $start = 0; // time();
        while (socket_select($read, $write, $except, $tv_sec, $tv_usec) > 0) {
            // will block to wait server response
            $this->bytesReceived += $bytesReceived = socket_recv($this->socket, $buffer, 65536, 0);
            if ($bytesReceived > 0) {
                if (($frame = $this->processFrame($buffer)) === true) {
                    continue;
                }
                if (false === $frame) {
                    break;
                }

                return $this->protocol->decode($frame, $payload);
            }
            if (-1 == $bytesReceived) {
                throw new \Exception('An error occured while receiving from the socket');
            }
            if (0 == $bytesReceived) {
                $this->disconnect();

                return false;
            }
            if (($start++) > 5) {
                return false;
            }
        }

        return null;
    }

    protected function processFrame(?string &$frameBuffer = null): bool|string
    {
        if ($this->frameBuffer) {
            $frameBuffer = $this->frameBuffer.$frameBuffer;
            $this->frameBuffer = null;

            return $this->processFrame($frameBuffer);
        }
        if (!$frameBuffer) {
            return false;
        }
        $payload = '';
        $opcode = $this->getFrame($frameBuffer, $payload);
        /*
         * If we get an opcode that equals false then we got a bad frame.
         *
         * If we get a opcode of -1 there are more frames to come for this payload. So, we return false if there are no
         * more frames to process, or true if there are already more frames in the buffer to process.
         */
        if (false === $opcode) {
            $this->disconnect();

            return false;
        }
        if (-1 === $opcode) {
            $this->frameBuffer .= $frameBuffer;

            return strlen($this->frameBuffer) > 0;
        }

        switch ($opcode) {
            case 0:
            case 1:
            case 2:
                break;

            case 8:
                if (false === $this->closing) {
                    $this->disconnect();
                }

                return false;

            case 9:
                $frame = $this->frame('', 'pong', false);
                @socket_write($this->socket, $frame, strlen($frame));

                return false;

            case 10:
                return false;

            default:
                $this->disconnect();

                return false;
        }
        if (strlen($frameBuffer) > 0) {
            $this->frameBuffer = $frameBuffer;
            $frameBuffer = '';
        }
        if ($this->payloadBuffer) {
            $payload = $this->payloadBuffer.$payload;
            $this->payloadBuffer = '';
        }

        return $payload;
    }
}
