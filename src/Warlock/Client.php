<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Application;

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
     * @param array<mixed> $config
     */
    public function __construct(
        array $config = []
    ) {
        $config = new Config(config: $config);
        $this->config = $config['client'];
        $this->config['encode'] ??= $config['server']['encode'];
        $this->config['serverId'] ??= $config['server']['id'];
        $protocol = new Protocol((string) $config['server']['id'], $this->config['encode']);
        parent::__construct($protocol);
    }

    protected function createConnection(Protocol $protocol, ?string $guid = null): Connection\Socket
    {
        $headers = [];
        if (null !== $this->config['admin']['key']) {
            $headers['Authorization'] = 'Apikey '.base64_encode($this->config['admin']['key']);
        }
        if (null === $this->config['client']['port']) {
            $this->config['client']['port'] = $this->config['server']['port'];
        }

        return new Connection\Socket($protocol, $guid);
    }
}
