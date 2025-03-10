<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Client;

use Hazaar\Util\Boolean;
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
    public static int $reconnectTimeout = 15;

    private string $accessKey = '';
    private int $lastConnectAttempt = 0;
    private bool $isRemote = false;

    public function __construct(string $clusterName, string $address, int $port = 8000, bool $isRemote = false)
    {
        $this->log = new Logger();
        $this->clusterName = $clusterName;
        $this->address = $address;
        $this->port = $port;
        $this->id = md5($this->address.':'.$this->port);
        $this->name = 'PEER_'.strtoupper($this->address).':'.$this->port;
        $this->isRemote = $isRemote;
        $this->log->write(W_DEBUG, 'PEER->CONSTRUCT: HOST='.$this->address.' PORT='.$this->port.' REMOTE='.Boolean::toString($this->isRemote));
    }

    public function __destruct()
    {
        $this->log->write(W_DEBUG, 'PEER->DESTRUCT: HOST='.$this->address.' PORT='.$this->port);
    }

    public function process(): void
    {
        if (false === $this->isConnected()) {
            if (self::STATUS_DISCONNECTED === $this->status) {
                $this->connect();
            } else {
                $this->log->write(W_DEBUG, 'Connection to peer '.$this->address.':'.$this->port.' is not connected');
                $this->status = self::STATUS_DISCONNECTED;
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
        if ((time() - $this->lastConnectAttempt) < self::$reconnectTimeout) {
            return;
        }
        $this->lastConnectAttempt = time();
        if (null !== $accessKey) {
            $this->accessKey = $accessKey;
        }
        $this->status = self::STATUS_CONNECTING;
        $this->log->write(W_DEBUG, "PEER->OPEN: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
        $connectFlags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $this->stream = @stream_socket_client('tcp://'.$this->address.':'.$this->port, $errno, $errstr, 30, $connectFlags);
        if (false !== $this->stream) {
            Master::$instance->clientAdd($this->stream, $this);
        } else {
            $this->status = self::STATUS_DISCONNECTED;
        }
    }

    public function disconnect(): bool
    {
        if (is_resource($this->stream)) {
            Master::$instance->clientRemove($this->stream);
            stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
            $this->log->write(W_DEBUG, "PEER->CLOSE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
            fclose($this->stream);
        }
        $this->status = self::STATUS_DISCONNECTED;
        if (true === $this->isRemote) {
            Master::$instance->cluster->removePeer($this);
        }

        return true;
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
                Master::$instance->trigger($payload->id, $payload->data, $this->name, $payload->trigger);

                return true;

            default:
                return parent::processCommand($command, $payload);
        }
    }
}
