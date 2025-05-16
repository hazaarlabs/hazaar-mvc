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
            'host' => $this->config['host'],
            'port' => $this->config['port'],
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
        $this->log->write('Subcribing to test event', LogLevel::DEBUG);
        $this->subscribe(
            event: 'test',
            callback: function (mixed $data): void {
                $this->log->write('Test event triggered', LogLevel::DEBUG);
                $this->log->write('Data: '.json_encode($data), LogLevel::DEBUG);
            }
        );

        return true;
    }

    public function exec(): void
    {
        $this->log->write('AGENT WAITING', LogLevel::INFO);
        $this->sleep(5);
        $this->trigger('test', 'Hello, World', true);
    }
}
