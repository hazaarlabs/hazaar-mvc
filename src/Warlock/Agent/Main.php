<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent;

use Hazaar\Warlock\Config;
use Hazaar\Warlock\Connection\Socket;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Interface\Connection;
use Hazaar\Warlock\Process;
use Hazaar\Warlock\Protocol;

class Main extends Process
{
    /**
     * Signals that we will capture.
     *
     * @var array<int, string>
     */
    public array $pcntlSignals = [
        SIGINT => 'SIGINT',
        SIGHUP => 'SIGHUP',
        SIGTERM => 'SIGTERM',
        SIGQUIT => 'SIGQUIT',
    ];

    /**
     * Main task queue.
     *
     * @var array<Task>
     */
    public array $tasks = [];
    protected bool $reconnect = true;

    /**
     * @var array<mixed>
     */
    private array $config;

    public function __construct(?string $configFile = null, string $env = 'development')
    {
        $config = new Config($configFile, env: $env);
        if (!isset($config['agent'])) {
            throw new \Exception('Agent configuration not found');
        }
        $this->config = $config['server'];
        if ($tz = $this->config['timezone']) {
            date_default_timezone_set(timezoneId: $tz);
        }
        parent::__construct(new Protocol((string) $this->config['id'], $this->config['encode']));
    }

    public function setSilent(): void
    {
        $this->log->setLevel(LogLevel::NONE);
    }

    public function createConnection(Protocol $protocol, ?string $guid = null): Connection
    {
        $connection = new Socket($protocol, $guid);
        $connection->configure([
            'host' => $this->config['host'] ?? '127.0.0.1',
            'port' => $this->config['port'] ?? 13080,
            'headers' => [
                'X-WARLOCK-AGENT-ID' => $this->config['id'] ?? 1234,
            ],
        ]);

        return $connection;
    }

    public function bootstrap(): self
    {
        $this->log->setLevel(LogLevel::fromString($this->config['log']['level']));
        $this->log->setPrefix('main');
        $this->log->write('Bootstrapping agent...', LogLevel::DEBUG);

        return $this;
    }

    public function init(): bool
    {
        $this->subscribe('test', function (mixed $data): void {
            $this->log->write('Received test event: '.json_encode($data), LogLevel::DEBUG);
        });

        return true;
    }

    public function exec(): void
    {
        $this->log->write('Nothing to do...', LogLevel::DEBUG);
        $this->sleep(10);
    }

    public function shutdown(): void
    {
        $this->log->write('Agent is shutting down', LogLevel::INFO);
    }
}
