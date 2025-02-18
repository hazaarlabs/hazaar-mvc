<?php

declare(strict_types=1);

namespace Hazaar\Socket;

use Hazaar\Socket\Exception\BindFailed;
use Hazaar\Socket\Exception\CreateFailed;
use Hazaar\Socket\Exception\ListenFailed;
use Hazaar\Socket\Exception\OptionFailed;

/**
 * The socket server class.
 *
 * Creates a socket server that listens on an address and port for incoming connections.
 */
abstract class Server
{
    protected int $maxBufferSize;
    protected false|\Socket $master;

    /**
     * @var array<\Socket>
     */
    protected array $sockets = [];
    protected bool $running = false;

    public function __construct(string $addr, int $port, int $bufferLength = 2048)
    {
        $this->maxBufferSize = $bufferLength;
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (false == $this->master) {
            throw new CreateFailed();
        }
        if (!socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1)) {
            throw new OptionFailed($this->master);
        }
        if (!socket_bind($this->master, $addr, $port)) {
            throw new BindFailed($this->master);
        }
        if (!socket_listen($this->master, 20)) {
            throw new ListenFailed($this->master);
        }
        $this->sockets[] = $this->master;
    }

    /**
     * Server main loop.
     */
    public function run(?int $timeout = null): void
    {
        $this->running = true;
        while (true === $this->running) {
            if (empty($this->sockets)) {
                $this->sockets[] = $this->master;
            }
            $read = $this->sockets;
            $write = $except = null;
            @socket_select($read, $write, $except, null);
            foreach ($read as $socket) {
                if ($socket == $this->master) {
                    $client = socket_accept($socket);
                    if (false === $client) {
                        // $this->stderr('Failed: socket_accept()');

                        continue;
                    }
                    $remoteAddr = null;
                    $remotePort = null;
                    socket_getpeername($client, $remoteAddr, $remotePort);
                    $localAddr = null;
                    $localPort = null;
                    socket_getsockname($client, $localAddr, $localPort);
                    if (false !== $this->connecting($remoteAddr, $remotePort, $localAddr, $localPort)) {
                        echo "Accepted\n";
                        $this->sockets[] = $client;
                        $this->connected($client);
                    }
                } else {
                    $buf = '';
                    $numBytes = socket_recv($socket, $buf, $this->maxBufferSize, 0);
                    if ($numBytes > 0) {
                        $this->process($buf);
                    } else {
                        socket_close($socket);
                        $this->closed($socket);
                    }
                }
            }
        }
    }

    /**
     * Incomming connection request handler.
     *
     * Event called when a connection request is received. Should return true or false indicating if the connection should be accepted.
     */
    abstract protected function connecting(string $remoteAddr, int $remotePort, string $localAddr, int $localPort): bool;

    /**
     * Incomming connection handlers.
     *
     * Event called when a connection is established and data can begin to be sent/received.
     */
    abstract protected function connected(\Socket $client): void;

    /**
     * Incoming data handler.
     *
     * Called immediately when data is recieved.
     */
    abstract protected function process(string $message): void;

    /**
     * Close connection handler.
     *
     * Called after the connection is closed.
     */
    abstract protected function closed(\Socket $client): void;
}
