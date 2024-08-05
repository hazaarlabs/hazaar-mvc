<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\Map;
use Hazaar\Warlock\Server\Client\Peer;

class Cluster
{
    private Logger $log;
    private Map $config;

    /**
     * @var array<Peer>
     */
    private array $peers = [];

    public function __construct(Logger $log, Map $config)
    {
        $this->log = $log;
        $this->config = $config;
    }

    public function start(): void
    {
        if (true !== $this->config['enabled']) {
            return;
        }
        $this->log->write(W_INFO, 'Initialising Cluster');
        if ($this->config->has('peers')) {
            $peers = $this->config->get('peers');
            foreach ($peers as $peerConfig) {
                if (false === strpos(':', $peerConfig)) {
                    $peerConfig .= ':8000';
                }
                list($address, $port) = explode(':', $peerConfig);
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
            $peer = new Peer($this->config['name'], $client->address, $client->port);
            $peer->stream = $client->stream;
            $peer->status = Peer::STATUS_STREAMING;
            Master::$instance->clientReplace($peer->stream, $peer);
        } else {
            $peer = $client;
        }
        $this->peers[] = $peer;
        $this->log->write(W_INFO, 'Peer added: '.$peer->name.' at '.$peer->address.':'.$peer->port);
    }

    public function process(): void
    {
        if (true !== $this->config['enabled']) {
            return;
        }
        foreach ($this->peers as $peer) {
            if (!$peer instanceof Peer) {
                continue;
            }
            $peer->process();
            $this->log->write(W_DEBUG, 'Peer status: '.$peer->status());
        }
    }

    public function sendEvent(string $eventID, string $triggerID, mixed $data): bool
    {
        foreach ($this->peers as $peer) {
            $peer->sendEvent($eventID, $triggerID, $data);
        }

        return true;
    }
}
