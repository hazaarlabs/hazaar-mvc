<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Client;

use Hazaar\Util\Boolean;
use Hazaar\Warlock\Enum\ClientType;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Enum\PeerStatus;
use Hazaar\Warlock\Logger;
use Hazaar\Warlock\Server\Client;
use Hazaar\Warlock\Server\Main;

class Peer extends Client
{
    public ClientType $type = ClientType::PEER;
    public PeerStatus $status = PeerStatus::DISCONNECTED;
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
        $this->log->write('PEER->CONSTRUCT: HOST='.$this->address.' PORT='.$this->port.' REMOTE='.Boolean::toString($this->isRemote), LogLevel::DEBUG);
    }

    public function __destruct()
    {
        $this->log->write('PEER->DESTRUCT: HOST='.$this->address.' PORT='.$this->port, LogLevel::DEBUG);
    }

    public function process(): void
    {
        if (false === $this->isConnected()) {
            if (PeerStatus::DISCONNECTED === $this->status) {
                $this->connect();
            } else {
                $this->log->write('Connection to peer '.$this->address.':'.$this->port.' is not connected', LogLevel::DEBUG);
                $this->status = PeerStatus::DISCONNECTED;
            }

            return;
        }

        $meta = stream_get_meta_data($this->stream);

        switch ($this->status) {
            case PeerStatus::DISCONNECTED:
                $this->connect();

                break;

            case PeerStatus::CONNECTING:
                if ($meta['timed_out']) {
                    $this->log->write('Connection to peer '.$this->address.' timed out', LogLevel::NOTICE);
                    $this->disconnect();
                } else {
                    $this->status = PeerStatus::CONNECTED;
                }

                break;

            case PeerStatus::CONNECTED:
                if ($meta['eof']) {
                    $this->log->write('Connection to peer '.$this->address.' closed unexpectedly', LogLevel::NOTICE);
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
                $this->status = PeerStatus::NEGOTIATING;

                break;

            case PeerStatus::NEGOTIATING:
            case PeerStatus::AUTHENTICATING:
            case PeerStatus::STREAMING:
                if ($meta['eof']) {
                    $this->log->write('Connection to peer '.$this->address.' closed unexpectedly', LogLevel::NOTICE);
                    $this->disconnect();
                }
        }
    }

    public function recv(string &$buf): void
    {
        if (PeerStatus::STREAMING === $this->status) {
            parent::recv($buf);
        } elseif (PeerStatus::NEGOTIATING === $this->status) {
            $lines = explode("\n", $buf);
            $lead = explode(' ', $lines[0], 3);
            if (!(isset($lead[1]) && '101' === $lead[1])) {
                $this->disconnect();
            } else {
                $this->log->write('Peer '.$this->address.' connected', LogLevel::INFO);
                $this->status = PeerStatus::STREAMING;
            }
        }
    }

    public function status(): string
    {
        return $this->status->toString();
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
        $this->status = PeerStatus::CONNECTING;
        $this->log->write("PEER->OPEN: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
        $connectFlags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $this->stream = @stream_socket_client('tcp://'.$this->address.':'.$this->port, $errno, $errstr, 30, $connectFlags);
        // if (false !== $this->stream) {
        //     $this->agent->clientAdd($this->stream, $this);
        // } else {
        //     $this->status = PeerStatus::DISCONNECTED;
        // }
    }

    public function disconnect(): bool
    {
        if (is_resource($this->stream)) {
            Main::$instance->clientRemove($this->stream);
            stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
            $this->log->write("PEER->CLOSE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
            fclose($this->stream);
        }
        $this->status = PeerStatus::DISCONNECTED;
        if (true === $this->isRemote) {
            // Main::$instance->cluster->removePeer($this);
        }

        return true;
    }

    public function isConnected(): bool
    {
        return is_resource($this->stream);
    }

    public function sendEvent(string $eventID, string $triggerID, mixed $data): bool
    {
        if (PeerStatus::STREAMING !== $this->status) {
            return false;
        }

        return parent::sendEvent($eventID, $triggerID, $data);
    }

    protected function processCommand(PacketType $command, mixed $payload = null): bool
    {
        if (PeerStatus::STREAMING !== $this->status) {
            return false;
        }

        switch ($command) {
            case 'INIT':
                $this->log->write('Received INIT command from peer '.$this->address.':'.$this->port, LogLevel::DEBUG);

                return true;

            case 'EVENT':
                $this->log->write('Received event from peer '.$this->address.':'.$this->port, LogLevel::DEBUG);
                Main::$instance->trigger($payload->id, $payload->data, $this->name, $payload->trigger);

                return true;

            default:
                return parent::processCommand($command, $payload);
        }
    }
}
