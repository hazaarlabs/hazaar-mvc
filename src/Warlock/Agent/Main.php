<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent;

use Hazaar\Config;
use Hazaar\Warlock\Agent\Struct\Application;
use Hazaar\Warlock\Agent\Struct\Endpoint;
use Hazaar\Warlock\Agent\Task\Internal;
use Hazaar\Warlock\Connection\Socket;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\TaskStatus;
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

    public function setSilent(): void
    {
        $this->log->setLevel(LogLevel::NONE);
    }

    public function createConnection(Protocol $protocol, ?string $guid = null): Connection
    {
        $connection = new Socket($protocol, $guid);
        $connection->configure([
            'host' => $this->config['server']['address'] ?? '127.0.0.1',
            'port' => $this->config['server']['port'] ?? 13080,
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
                    $endpoint->params = $args->toArray();
                }
                $when = $task['when'] ?? null;
                if ('@reboot' === strtolower($when)) {
                    $when = time() + ($this->config['task']['boot_delay'] ?? 0);
                }
                $this->scheduleRunner($when, $endpoint, $task['tag'] ?? uniqid(), $task['overwrite'] ?? false);
            }
        }

        return $this;
    }

    public function exec(): void
    {
        foreach ($this->tasks as $task) {
            $task->process();
        }
        $this->sleep(1);
    }

    public function shutdown(): void
    {
        $this->log->write('Agent is shutting down', LogLevel::INFO);
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
        $task = new Task\Runner($this,$this->log);
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
        // Stop the task if it is currently running
        if (TaskStatus::RUNNING === $task->status && $task->isRunning()) {
            // $this->log->write('Stopping running '.$task->type, LogLevel::NOTICE);
            $task->terminate();
        }
        $task->status = TaskStatus::CANCELLED;
        // Expire the task in 30 seconds
        $task->expire = time() + $this->config['task']['expire'];

        return true;
    }

    /**
     * Main process loop.
     *
     * This method will monitor and manage queued running tasks.
     */
    private function taskProcess(): void
    {
        foreach ($this->tasks as $id => &$task) {
            switch ($task->status) {
                case TaskStatus::QUEUED:
                case TaskStatus::RESTART:
                case TaskStatus::RETRY: // Tasks that are queued and ready to execute or ready to restart an execution retry.
                    if ($task->ready()) {
                        // if ($this->stats['processes'] >= $this->config['process']['limit']) {
                        //     ++$this->stats['limitHits'];
                        //     $this->log->write('Process limit of '.$this->config['process']['limit'].' processes reached!', LogLevel::WARN);

                        //     break;
                        // }
                        $task->start();
                    }

                    if (TaskStatus::STARTING !== $task->status) {
                        break;
                    }

                    // no break
                case TaskStatus::STARTING:
                    $task->run();
                    $pipe = $task->getReadPipe();
                    $pipeID = (int) $pipe;
                    $this->streams[$pipeID] = $pipe;

                    break;

                case TaskStatus::CANCELLED:
                    if ($task->expired()) {
                        $task->terminate();

                        break;
                    }

                    // no break
                case TaskStatus::RUNNING:
                    $processes++;
                    $this->taskMonitor($task);
                    if ($task->timeout()) {
                        $this->log->write('Process taking too long to execute - Attempting to kill it.', LogLevel::WARN);
                        if ($task->terminate()) {
                            $this->log->write('Terminate signal sent.', LogLevel::DEBUG);
                        } else {
                            $this->log->write('Failed to send terminate signal.', LogLevel::ERROR);
                        }
                    }

                    break;

                case TaskStatus::COMPLETE:
                    if (($next = $task->touch()) > time()) {
                        $task->status = TaskStatus::QUEUED;
                        $task->retries = 0;
                        $this->log->write('Next execution at: '.date($this->config['sys']['dateFormat'], $next), LogLevel::NOTICE);
                    } else {
                        $task->status = TaskStatus::WAIT;
                        // Expire the task in 30 seconds
                        $task->expire = time() + $this->config['task']['expire'];
                    }

                    break;

                case TaskStatus::ERROR:
                    if ($task->retries < $this->config['task']['retries']) {
                        $this->log->write('Task failed. Retrying in '.$this->config['task']['retry'].' seconds', LogLevel::NOTICE);
                        $task->status = TaskStatus::RETRY;
                    } else {
                        $this->log->write('Task execution failed', LogLevel::ERROR);
                        $task->status = TaskStatus::WAIT;
                        $task->expire = time() + $this->config['task']['expire'];
                    }

                    break;

                case TaskStatus::WAIT:
                    if ($task->expired()) {
                        $this->log->write('Cleaning up', LogLevel::NOTICE);
                        unset($this->tasks[$id]);
                    }
            }
        }
    }
}
