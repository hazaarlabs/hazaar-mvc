<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Application;
use Hazaar\Config;

/**
 * @brief       Control class for Warlock
 *
 * @detail      This class creates a connection to the Warlock server from within a Hazaar application allowing the
 *              application to send triggers or schedule tasks for delayed execution.
 *
 * @module      warlock
 */
class Client extends Process
{
    /**
     * @var array<string,mixed>
     */
    public array $config;

    /**
     * @param array<string,mixed> $clientConfig
     */
    public function __construct(
        array $clientConfig = []
    ) {
        $config = new Config('warlock');
        $config->loadFromArray(['client' => $clientConfig], [
            'client' => [
                'encode' => false,
            ],
        ]);
        $this->config = $config['client'] ?? [];
        $this->config['encode'] ??= $config['server']['encode'];
        $this->config['serverId'] ??= (string) ($config['server']['id'] ?? 1);
        $protocol = new Protocol($this->config['serverId'], $this->config['encode']);
        parent::__construct($protocol);
        $this->conn->configure($this->config);
    }

    public function wait(int $seconds = 0): void
    {
        $command = $this->recv($payload, $seconds);
        $this->processCommand($command, $payload);
    }

    protected function createConnection(Protocol $protocol, ?string $guid = null): Connection\Socket
    {
        $headers = [];
        if (isset($this->config['adminKey'])) {
            $headers['Authorization'] = 'Apikey '.base64_encode($this->config['adminKey']);
        }
        if (isset($this->config['port'])) {
            $this->config['client']['port'] = $this->config['server']['port'];
        }

        return new Connection\Socket($protocol, $guid, $headers);
    }
}
