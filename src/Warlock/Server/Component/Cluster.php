<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Component;

use Hazaar\Warlock\Server\Client;
use Hazaar\Warlock\Server\Client\Peer;
use Hazaar\Warlock\Server\Enum\LogLevel;
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

    public function start(): void
    {
        if (true !== $this->config['enabled']) {
            return;
        }
        $this->log->write('Starting Cluster Manager', LogLevel::INFO);
        if (!isset($this->config['peers'])) {
            return;
        }
        Peer::$reconnectTimeout = $this->config['peerReconnect'] ?? 30;
        $peers = $this->config['peers'];
        if (is_array($peers) && 0 === count($peers)) {
            $this->log->write('No peers defined in cluster configuration', LogLevel::INFO);

            return;
        }
        foreach ($peers as $peerConfig) {
            if (false === strpos(':', $peerConfig)) {
                $peerConfig .= ':8000';
            }
            [$address, $port] = explode(':', $peerConfig);
            if (false === is_numeric($port)) {
                $port = 8000;
            }
            $peer = new Peer($this->config['name'], $address, intval($port));
            $peer->connect($this->config['accessKey']);
            $this->peers[] = $peer;
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

    /**
     * @param array<string, string> $headers
     */
    public function addPeer(array $headers, Client $client): void
    {
        if (!$client instanceof Peer) {
            $peer = new Peer($this->config['name'], $client->address, $client->port, true);
            $peer->stream = $client->stream;
            $peer->status = Peer::STATUS_STREAMING;
            Main::$instance->clientReplace($peer->stream, $peer);
        } else {
            $peer = $client;
        }
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
}
