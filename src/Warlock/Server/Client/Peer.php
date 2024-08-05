<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Client;

use Hazaar\Warlock\Server\Client;
use Hazaar\Warlock\Server\Logger;
use Hazaar\Warlock\Server\Master;

class Peer extends Client
{
    public const STATUS_DISCONNECTED = 0;
    public const STATUS_CONNECTING = 1;
    public const STATUS_CONNECTED = 2;
    public const STATUS_NEGOTIATING = 3;
    public const STATUS_AUTHENTICATING = 4;
    public const STATUS_STREAMING = 5;

    public string $type = 'peer';
    public int $status = self::STATUS_DISCONNECTED;
    public string $clusterName;

    private string $accessKey = '';

    public function __construct(string $clusterName, string $address, int $port = 8000)
    {
        $this->log = new Logger();
        $this->clusterName = $clusterName;
        $this->address = $address;
        $this->port = $port;
        $this->id = md5($this->address.':'.$this->port);
        $this->name = 'PEER_'.strtoupper($this->address).':'.$this->port;
    }

    // public function close(): void
    // {
    //     $this->log->write(W_DEBUG, 'Closing peer connection to '.$this->address.':'.$this->port);
    //     $this->status = self::STATUS_DISCONNECTED;
    //     if (is_resource($this->stream)) {
    //         stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
    //         Master::$instance->clientRemove($this->stream);
    //     }
    //     $this->stream = null;
    // }

    public function process(): void
    {
        if (false === $this->isConnected()) {
            if (self::STATUS_DISCONNECTED === $this->status) {
                $this->status = self::STATUS_CONNECTING;
                $this->connect();
            } else {
                $this->log->write(W_NOTICE, 'Connection to peer '.$this->address.':'.$this->port.' is not connected');
                $this->disconnect();
            }

            return;
        }

        $meta = stream_get_meta_data($this->stream);

        switch ($this->status) {
            case self::STATUS_DISCONNECTED:
                $this->connect();

                break;

            case self::STATUS_CONNECTING:
                if ($meta['timed_out']) {
                    $this->log->write(W_NOTICE, 'Connection to peer '.$this->address.' timed out');
                    $this->disconnect();
                } else {
                    $this->status = self::STATUS_CONNECTED;
                }

                break;

            case self::STATUS_CONNECTED:
                if ($meta['eof']) {
                    $this->log->write(W_NOTICE, 'Connection to peer '.$this->address.' closed unexpectedly');
                    $this->disconnect();
                }
                $packet = "GET /hazaar/warlock HTTP/1.1\n";
                $packet .= "Host: localhost:8000\n";
                $packet .= "Connection: Upgrade\n";
                $packet .= "Pragma: no-cache\n";
                $packet .= "Cache-Control: no-cache\n";
                $packet .= "Upgrade: websocket\n";
                $packet .= "Sec-WebSocket-Version: 13\n";
                $packet .= "Sec-WebSocket-Key: ZGeo6LNqIDnNU9TJSHW0Qw==\n";
                $packet .= "Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits\n";
                $packet .= "Sec-WebSocket-Protocol: warlock\n";
                $packet .= 'X-Cluster-Name: '.$this->clusterName."\n";
                $packet .= 'X-Cluster-Access-Key: '.$this->accessKey."\n";
                $packet .= 'X-Client-Name: '.$this->name."\n";
                $this->write($packet."\n");
                $this->status = self::STATUS_NEGOTIATING;

                break;

            case self::STATUS_NEGOTIATING:
            case self::STATUS_AUTHENTICATING:
            case self::STATUS_STREAMING:
                if ($meta['eof']) {
                    $this->log->write(W_NOTICE, 'Connection to peer '.$this->address.' closed unexpectedly');
                    $this->disconnect();
                }
        }
    }

    public function recv(string &$buf): void
    {
        if (self::STATUS_STREAMING === $this->status) {
            parent::recv($buf);
        } elseif (self::STATUS_NEGOTIATING === $this->status) {
            $lines = explode("\n", $buf);
            $lead = explode(' ', $lines[0], 3);
            if (!(isset($lead[1]) && '101' === $lead[1])) {
                $this->disconnect();
            } else {
                $this->log->write(W_INFO, 'Peer '.$this->address.' connected');
                $this->status = self::STATUS_STREAMING;
            }
        } else {
            $this->log->write(W_NOTICE, 'Received data from peer '.$this->address.' while not streaming');
        }
    }

    public function status(): string
    {
        return match ($this->status) {
            self::STATUS_DISCONNECTED => 'Disconnected',
            self::STATUS_CONNECTING => 'Connecting',
            self::STATUS_CONNECTED => 'Connected',
            self::STATUS_NEGOTIATING => 'Negotiating',
            self::STATUS_AUTHENTICATING => 'Authenticating',
            self::STATUS_STREAMING => 'Streaming',
            default => 'Unknown'
        };
    }

    public function connect(?string $accessKey = null): void
    {
        if (null !== $accessKey) {
            $this->accessKey = $accessKey;
        }
        $this->status = self::STATUS_CONNECTING;
        $this->log->write(W_DEBUG, 'Connecting to peer '.$this->address.':'.$this->port);
        $this->stream = stream_socket_client('tcp://'.$this->address.':'.$this->port, $errno, $errstr, STREAM_CLIENT_ASYNC_CONNECT);
        Master::$instance->clientAdd($this->stream, $this);
    }

    public function isConnected(): bool
    {
        return is_resource($this->stream);
    }

    public function sendEvent(string $eventID, string $triggerID, mixed $data): bool
    {
        if (self::STATUS_STREAMING !== $this->status) {
            return false;
        }

        return parent::sendEvent($eventID, $triggerID, $data);
    }

    protected function processCommand(string $command, mixed $payload = null): bool
    {
        if (self::STATUS_STREAMING !== $this->status) {
            return false;
        }

        switch ($command) {
            case 'INIT':
                $this->log->write(W_DEBUG, 'Received INIT command from peer '.$this->address.':'.$this->port);

                return true;

            case 'EVENT':
                $this->log->write(W_DEBUG, 'Received event from peer '.$this->address.':'.$this->port);
                Master::$instance->trigger($payload->id, $payload->data);

                return true;

            default:
                return parent::processCommand($command, $payload);
        }
    }
}
