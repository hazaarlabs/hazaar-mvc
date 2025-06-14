<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Component;

use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Enum\PeerStatus;
use Hazaar\Warlock\Logger;
use Hazaar\Warlock\Server\Client\Peer;
use Hazaar\Warlock\Server\Main;

class Cluster
{
    /**
     * @var array<Peer>
     */
    public array $peers = [];
    private Logger $log;

    /**
     * @var array<mixed>
     */
    private array $config;

    /**
     * @param array<mixed> $config
     */
    public function __construct(Logger $log, array $config)
    {
        $this->log = $log;
        $this->config = $config;
    }

    public function start(Main $main): void
    {
        if (true !== $this->config['enabled']) {
            return;
        }
        $this->log->write('Starting Cluster Manager', LogLevel::INFO);
        if (!isset($this->config['peers'])) {
            return;
        }
        $this->peers = [];
        $peers = $this->config['peers'];
        if (is_array($peers) && 0 === count($peers)) {
            $this->log->write('No peers defined in cluster configuration', LogLevel::INFO);

            return;
        }
        foreach ($peers as $peerConfig) {
            $this->log->write("CLUSTER->PEER: HOST={$peerConfig}", LogLevel::DEBUG);
            $peer = new Peer($main, null, array_merge($this->config, ['peer' => $peerConfig]));
            if ($peer->connect()) {
                $peer->process();
                $this->peers[] = $peer;
            }
        }
    }

    public function stop(): void
    {
        if (true !== $this->config['enabled']) {
            return;
        }
        $this->log->write('Shutting down Cluster', LogLevel::INFO);
        foreach ($this->peers as $peer) {
            $peer->disconnect();
        }
    }

    public function addPeer(Peer $peer): void
    {
        if (array_key_exists($peer->name, $this->peers)) {
            $this->log->write('Peer already exists: '.$peer->name.' at '.$peer->address.':'.$peer->port, LogLevel::NOTICE);

            return;
        }
        if (!$peer->isConnected()) {
            $this->log->write("Peer is not connected: {$peer->name} at {$peer->address}:{$peer->port}", LogLevel::WARN);

            return;
        }
        $peer->status = PeerStatus::NEGOTIATING;
        $this->peers[$peer->name] = $peer;
        $this->log->write('Peer added: '.$peer->name.' at '.$peer->address.':'.$peer->port, LogLevel::INFO);
    }

    public function removePeer(Peer $peer): void
    {
        if (array_key_exists($peer->name, $this->peers)) {
            unset($this->peers[$peer->name]);
            $this->log->write('Peer removed: '.$peer->name.' at '.$peer->address.':'.$peer->port, LogLevel::INFO);
        }
    }

    public function process(): void
    {
        if (true !== $this->config['enabled']) {
            return;
        }
        foreach ($this->peers as $peer) {
            $peer->process();
        }
    }

    public function processCommand(Peer $peer, PacketType $command, ?\stdClass $payload = null): void
    {
        switch ($command) {
            case PacketType::PEERINFO:
                $this->log->write("Processing PEERINFO command for {$peer->name}", LogLevel::DEBUG);
                $peer->address = $payload->address ?? $peer->address;
                $peer->port = $payload->port ?? $peer->port;
                $this->log->write("Updated peer status: {$peer->name} at {$peer->address}:{$peer->port}", LogLevel::DEBUG);

                // Handle peer info command
                break;

            case PacketType::PEERSTATUS:
                $this->log->write("Processing PEERSTATUS command for {$peer->name}", LogLevel::DEBUG);

                // Handle peer status update
                break;

            default:
                $this->log->write("Unknown command: {$command->name}", LogLevel::WARN);

                break;
        }
    }
}
