<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent;

use Hazaar\Application\FilePath;
use Hazaar\Config;
use Hazaar\Loader;
use Hazaar\Warlock\Agent\Struct\Application;
use Hazaar\Warlock\Agent\Struct\Endpoint;
use Hazaar\Warlock\Agent\Task\Internal;
use Hazaar\Warlock\Connection\Socket;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Enum\PcntlSignals;
use Hazaar\Warlock\Enum\TaskStatus;
use Hazaar\Warlock\Interface\Connection;
use Hazaar\Warlock\Process;
use Hazaar\Warlock\Protocol;

class Main extends Process
{
    /**
     * Main task queue.
     *
     * @var array<Task>
     */
    public array $tasks = [];

    /**
     * Tags for scheduled tasks.
     *
     * @var array<string, Task>
     */
    public array $tags = [];
    protected bool $reconnect = true;

    private Config $config;

    private Application $application;

    public function __construct(?string $applicationPath = null, ?string $configFile = null, string $env = 'development')
    {
        if (null === $applicationPath || !is_dir($applicationPath)) {
            throw new \InvalidArgumentException("Application path '{$applicationPath}' does not exist or is not a directory.");
        }
        $loader = Loader::createInstance($applicationPath);
        $loader->addSearchPath(FilePath::SERVICE, 'services');
        $loader->register();
        $this->application = new Application($applicationPath, $env);
        $this->config = new Config();
        $this->config->setBasePath($applicationPath.DIRECTORY_SEPARATOR.'configs');
        $this->config->setEnvironment($env);
        $this->config->loadFromFile($configFile, [
            'server' => [
                'address' => '127.0.0.1',
                'port' => 13080,
            ],
            'encode' => false, // Use JSON encoding for the protocol.
            'id' => uniqid(), // Unique ID for this agent.
            'timezone' => 'UTC',
            'log' => [
                'level' => 'DEBUG', // Default log level.
            ],
            'task' => [
                'retries' => 3,    // Retry tasks that failed this many times.
                'retry' => 10,     // Retry failed tasks after this many seconds.
                'expire' => 10,    // Completed tasks will be cleaned up from the task queue after this many seconds.
                'boot_delay' => 5, // How long to hold off executing tasks scheduled to run on a reboot.  Can be used to allow services to finish starting.
            ],
            'process' => [
                'timeout' => 30,   // Timeout for short run tasks initiated by the front end. Prevents runaway processes from hanging around.
                'limit' => 5,      // Maximum number of concurrent tasks to execute.  THIS INCLUDES SERVICES.  So if this is 5 and you have 6 services, one service will never run!
                'exitWait' => 30,  // How long the server will wait for processes to exit when shutting down.
            ],
            'service' => [
                'restarts' => 5,   // Restart a failed service this many times before disabling it for a bit.
                'disable' => 300,  // Disable a failed service for this many seconds before trying to start it up again.
            ],
        ]);
        if (!isset($this->config['server'])) {
            throw new \Exception('Agent configuration not valid');
        }
        if ($tz = $this->config['timezone']) {
            date_default_timezone_set(timezoneId: $tz);
        }
        parent::__construct(new Protocol((string) $this->config['id'], $this->config['encode']));
    }

    /**
     * Returns the agent configuration.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    public function setSilent(bool $silent = true): void
    {
        $this->log->setSilent($silent);
    }

    public function createConnection(Protocol $protocol, ?string $guid = null): Connection
    {
        $connection = new Socket($protocol, $guid);
        $connection->configure([
            'host' => $this->config['server']['address'] ?? '127.0.0.1',
            'port' => $this->config['server']['port'] ?? 13080,
            'headers' => [
                'X-WARLOCK-TYPE' => 'agent',
                'X-WARLOCK-ACCESS-KEY' => $this->config['id'] ?? 1234,
            ],
        ]);

        return $connection;
    }

    public function bootstrap(): self
    {
        $this->log->setLevel(LogLevel::fromString($this->config['log']['level']));
        $this->log->setPrefix('agent');
        $this->log->write('Warlock agent starting up', LogLevel::NOTICE);
        foreach (PcntlSignals::cases() as $sig) {
            pcntl_signal($sig->value, [$this, '__signalHandler'], true);
        }
        if ($this->config['announce']['enabled'] ?? false) {
            $task = new Internal($this, $this->log);
            $task->schedule('* * * * *');
            $task->exec(Endpoint::create([$this, 'announce']));
            $this->taskQueueAdd($task);
        }
        if (isset($this->config['schedule'])) {
            $this->log->write('Scheduling '.count($this->config['schedule']).' tasks', LogLevel::NOTICE);
            foreach ($this->config['schedule'] as $task) {
                if (!isset($task['action'])) {
                    $this->log->write('Warlock schedule config has no \'action\' attribute.', LogLevel::ERROR);

                    continue;
                }
                if (!($endpoint = Endpoint::create($task['action'] ?? null))) {
                    $this->log->write('Warlock schedule config contains invalid callable.', LogLevel::ERROR);

                    continue;
                }
                if ($args = $task['args'] ?? null) {
                    $endpoint->setParams($args->toArray());
                }
                $when = $task['when'] ?? null;
                if ('@reboot' === strtolower($when)) {
                    $when = time() + ($this->config['task']['boot_delay'] ?? 0);
                }
                $this->scheduleRunner($when, $endpoint, $task['tag'] ?? uniqid(), $task['overwrite'] ?? false);
            }
        }
        $this->registerServices('services');
        $this->log->write(
            'Connecting to '.$this->conn->getHost().':'.$this->conn->getPort(),
            LogLevel::INFO
        );

        return $this;
    }

    public function registerServices(string $configFile = 'services'): void
    {
        $services = new Config();
        $services->setBasePath($this->config->getBasePath());
        $services->setEnvironment($this->config->getEnvironment());
        $services->loadFromFile($configFile);
        if (0 === $services->count()) {
            return;
        }
        $this->log->write('Loading '.count($services).' services', LogLevel::NOTICE);
        foreach ($services as $serviceName => $service) {
            if (!isset($service['target'])) {
                $serviceClassName = 'App\Service\\'.ucfirst($serviceName);
                $service['target'] = [$serviceClassName, 'main'];
                $this->log->write("Using service class {$serviceClassName}", LogLevel::DEBUG);
            }
            if (!($endpoint = Endpoint::create($service['target'] ?? null))) {
                $this->log->write('Warlock service config contains invalid callable.', LogLevel::ERROR);

                continue;
            }
            if ($args = $service['args'] ?? null) {
                $endpoint->setParams($args->toArray());
            }
            $task = new Task\Service($this, $this->log, $service);
            $task->exec($endpoint);
            $this->taskQueueAdd($task);
        }
    }

    public function shutdown(): void
    {
        $this->log->write('Agent is shutting down', LogLevel::NOTICE);
        if (count($this->tasks) > 0) {
            $this->log->write('Cancelling all tasks before shutdown', LogLevel::NOTICE);
            foreach ($this->tasks as $task) {
                $task->cancel();
            }
            $this->tasks = [];
            // Wait for tasks to finish
            $this->log->write('Waiting for tasks to finish...', LogLevel::NOTICE);
            $waitTime = 0;
            while (count($this->tasks) > 0 && $waitTime < ($this->config['process']['exitWait'] ?? 30)) {
                $this->run();
                ++$waitTime;
            }
        }
        if (count($this->tasks) > 0) {
            $this->log->write('Some tasks did not finish in time, forcing shutdown', LogLevel::ERROR);
            foreach ($this->tasks as $task) {
                $task->cancel();
            }
            $this->tasks = [];
        }
        $this->log->write('Agent shutdown complete', LogLevel::NOTICE);
    }

    public function init(): bool
    {
        $this->log->write('Agent ready', LogLevel::NOTICE);

        return true;
    }

    public function announce(): void
    {
        $this->log->write(
            'TASKS='.count($this->tasks)
            // .' PROCESSES='.$this->stats['processes']
            // .' SERVICES='.count($this->services)
            .' SUBSCRIPTIONS='.count($this->subscriptions),
            LogLevel::INFO
        );
    }

    protected function run(): void
    {
        foreach ($this->tasks as $task) {
            $task->process();
            if ($task->expired()) {
                $this->log->write('Cleaning up expired task', LogLevel::NOTICE);
                unset($this->tasks[$task->id]);
            }
        }
        $this->sleep(1);
    }

    protected function processCommand(PacketType $command, ?\stdClass $payload = null): bool
    {
        switch ($command) {
            case PacketType::CANCEL:
                return $this->taskCancel($payload->taskID ?? '');

            case PacketType::DELAY:
                if (!isset($payload->value)) {
                    $this->log->write('Invalid payload for DELAY command', LogLevel::ERROR);

                    return false;
                }
                $task = (new Task\Runner($this, $this->log))
                    ->schedule(time() + $payload->value)
                    ->exec(Endpoint::create($payload->endpoint))
                ;
                $this->taskQueueAdd($task);
                $this->send(PacketType::OK, [
                    'message' => 'Task delayed successfully',
                    'taskID' => $task->id,
                ]);
        }

        return parent::processCommand($command, $payload);
    }

    private function scheduleRunner(
        int|string $when,
        Endpoint $endpoint,
        ?string $tag = null,
        bool $overwrite = false
    ): false|string {
        if ($tag && array_key_exists($tag, $this->tags)) {
            $task = $this->tags[$tag];
            $this->log->write("Task already scheduled with tag {$tag}", LogLevel::NOTICE);
            if (false === $overwrite) {
                $this->log->write('Skipping', LogLevel::NOTICE);

                return false;
            }
            $this->log->write('Overwriting', LogLevel::NOTICE);
            $task->cancel();
            unset($this->tags[$tag], $this->tasks[$task->id]);
        }
        $task = new Task\Runner($this, $this->log);
        $task->schedule($when);
        $task->exec($endpoint);
        $this->log->write("TASK: ID={$task->id}", LogLevel::DEBUG);
        $this->log->write('WHEN: '.date('c', $task->start), LogLevel::DEBUG);
        $this->log->write('APPLICATION_ENV: '.$this->application->env, LogLevel::DEBUG);
        if ($tag) {
            $this->log->write('TAG: '.$tag, LogLevel::DEBUG);
            $this->tags[$tag] = $task;
        }
        $this->taskQueueAdd($task);

        return $task->id;
    }

    private function taskQueueAdd(Task $task): void
    {
        if (array_key_exists($task->id, $this->tasks)) {
            $this->log->write('Process already exists in queue!', LogLevel::WARN);

            return;
        }
        $task->application = $this->application;
        $task->config = $this->config['task'];
        $this->tasks[$task->id] = $task;
        // ++$this->stats['tasks'];
        // $this->admins[$task->id] = $task; // Make all processes admins so they can issue delay/schedule/etc.
        $this->log->write('TASK->QUEUE: START='.date('c', $task->start)
            .($task->tag ? " TAG={$task->tag}" : ''), LogLevel::DEBUG);
        $this->log->write('APPLICATION_PATH: '.$task->application->path, LogLevel::DEBUG);
        $this->log->write('APPLICATION_ENV:  '.$task->application->env, LogLevel::DEBUG);
        $task->status = TaskStatus::QUEUED;
    }

    private function taskCancel(string $taskID): bool
    {
        $this->log->write('Trying to cancel task', LogLevel::DEBUG);
        // If the task IS is not found return false
        if (!array_key_exists($taskID, $this->tasks)) {
            return false;
        }
        $task = &$this->tasks[$taskID];
        if ($task->tag) {
            unset($this->tags[$task->tag]);
        }
        $task->cancel();

        return true;
    }
}
