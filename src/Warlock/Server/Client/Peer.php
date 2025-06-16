<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Client;

use Hazaar\Warlock\Enum\ClientType;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Enum\PeerStatus;
use Hazaar\Warlock\Server\Client;
use Hazaar\Warlock\Server\Main;

class Peer extends Client
{
    public ClientType $type = ClientType::PEER;
    public PeerStatus $status = PeerStatus::DISCONNECTED;
    private int $reconnectTimeout = 15;

    private int $lastConnectAttempt = 0;

    public function __construct(Main $main, mixed $stream = null, ?array $options = null)
    {
        parent::__construct($main, $stream, $options);
        if (null === $stream) {
            $peerConfig = $options['peer'] ?? '127.0.1:13080';
            if (false === strpos($peerConfig, ':')) {
                $peerConfig .= ':13080'; // Default port if not specified
            }
            [$this->address, $port] = explode(':', $peerConfig);
            if (false === is_numeric($port)) {
                $port = 13080; // Default port if not numeric
            }
            $this->port = (int) $port;
        }
        $this->main->log->write('PEER->CONSTRUCT: HOST='.$this->address.' PORT='.$this->port, LogLevel::DEBUG);
        $this->id = $options['id'] ?? uniqid('peer_');
        $this->reconnectTimeout = $options['peerReconnect'] ?? 15; // Default reconnect timeout
    }

    public function __destruct()
    {
        $this->main->log->write('PEER->DESTRUCT: HOST='.$this->address.' PORT='.$this->port, LogLevel::DEBUG);
    }

    public function initiateHandshake(string $request, array &$headers = []): bool
    {
        if (parent::initiateHandshake($request, $headers)) {
            $this->status = PeerStatus::STREAMING;
            $this->main->log->write('Peer '.$this->address.' connected', LogLevel::INFO);

            return true;
        }
        $this->status = PeerStatus::DISCONNECTED;
        $this->main->log->write('Failed to connect to peer '.$this->address.':'.$this->port, LogLevel::ERROR);

        return false;
    }

    public function process(): void
    {
        if (!is_resource($this->stream)) {
            if (!$this->connect()) {
                return;
            }
        }
        $meta = stream_get_meta_data($this->stream);

        switch ($this->status) {
            case PeerStatus::DISCONNECTED:
                $this->connect();

                break;

            case PeerStatus::CONNECTING:
                if ($meta['timed_out']) {
                    $this->main->log->write('Connection to peer '.$this->address.' timed out', LogLevel::NOTICE);
                    $this->disconnect();

                    return;
                }
                $this->status = PeerStatus::CONNECTED;

                // no break
            case PeerStatus::CONNECTED:
                if ($meta['eof']) {
                    $this->main->log->write('Connection to peer '.$this->address.' closed unexpectedly', LogLevel::NOTICE);
                    $this->disconnect();
                }
                $packet = "GET /warlock HTTP/1.1\n";
                $packet .= "Host: localhost:8000\n";
                $packet .= "Connection: Upgrade\n";
                $packet .= "Pragma: no-cache\n";
                $packet .= "Cache-Control: no-cache\n";
                $packet .= "Upgrade: websocket\n";
                $packet .= "Sec-WebSocket-Version: 13\n";
                $packet .= "Sec-WebSocket-Key: ZGeo6LNqIDnNU9TJSHW0Qw==\n";
                $packet .= "Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits\n";
                $packet .= "Sec-WebSocket-Protocol: warlock\n";
                $packet .= "X-Warlock-Type: peer\n";
                $packet .= 'X-Cluster-Name: '.($this->config['clusterName'] ?? '')."\n";
                $packet .= 'X-Cluster-Access-Key: '.($this->config['accessKey'] ?? '')."\n";
                // $packet .= 'X-Client-Name: '.$this->name."\n";
                $this->write($packet."\n");
                $this->status = PeerStatus::NEGOTIATING;

                break;

            case PeerStatus::NEGOTIATING:
            case PeerStatus::AUTHENTICATING:
            case PeerStatus::STREAMING:
                if ($meta['eof']) {
                    $this->main->log->write('Connection to peer '.$this->address.' closed unexpectedly', LogLevel::NOTICE);
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
                $this->main->log->write('Peer '.$this->address.' connected', LogLevel::INFO);
                $this->status = PeerStatus::STREAMING;
            }
        }
    }

    public function status(): string
    {
        return $this->status->toString();
    }

    public function connect(): bool
    {
        if ((time() - $this->lastConnectAttempt) < $this->reconnectTimeout) {
            return false;
        }
        $this->lastConnectAttempt = time();
        $this->status = PeerStatus::CONNECTING;
        $this->main->log->write("PEER->OPEN: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
        $connectFlags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $this->stream = @stream_socket_client('tcp://'.$this->address.':'.intval($this->port), $errno, $errstr, 5, $connectFlags);

        return $this->main->clientAdd($this);
    }

    public function disconnect(): bool
    {
        if (is_resource($this->stream)) {
            $this->main->clientRemove($this->stream);
            stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
            $this->main->log->write("PEER->CLOSE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
            fclose($this->stream);
        }
        $this->status = PeerStatus::DISCONNECTED;

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
            case PacketType::INIT:
                $this->main->log->write('Received INIT command from peer '.$this->address.':'.$this->port, LogLevel::DEBUG);
                $this->send(PacketType::PEERINFO, [
                    'id' => $this->id,
                    'name' => $this->name,
                    'address' => '127.0.0.1',
                    'port' => 8001,
                ]);

                return true;

            case PacketType::EVENT:
                $this->main->log->write('Received event from peer '.$this->address.':'.$this->port, LogLevel::DEBUG);
                $this->main->trigger($payload->id, $payload->data, $this->name, $payload->trigger);

                return true;

            default:
                return parent::processCommand($command, $payload);
        }
    }
}
