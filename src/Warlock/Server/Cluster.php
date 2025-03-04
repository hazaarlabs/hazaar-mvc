<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\Warlock\Server\Client\Peer;

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
        $this->log->write(W_INFO, 'Starting Cluster Manager');
        if (isset($this->config['peers'])) {
            Peer::$reconnectTimeout = $this->config['peerReconnect'] ?? 30;
            $peers = $this->config['peers'];
            if (is_array($peers) && 0 === count($peers)) {
                $this->log->write(W_INFO, 'No peers defined in cluster configuration');

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
    }

    public function stop(): void
    {
        if (true !== $this->config['enabled']) {
            return;
        }
        $this->log->write(W_INFO, 'Shutting down Cluster');
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
            Master::$instance->clientReplace($peer->stream, $peer);
        } else {
            $peer = $client;
        }
        $this->peers[$peer->name] = $peer;
        $this->log->write(W_INFO, 'Peer added: '.$peer->name.' at '.$peer->address.':'.$peer->port);
    }

    public function removePeer(Peer $peer): void
    {
        if (array_key_exists($peer->name, $this->peers)) {
            unset($this->peers[$peer->name]);
            $this->log->write(W_INFO, 'Peer removed: '.$peer->name.' at '.$peer->address.':'.$peer->port);
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
