<?php

declare(strict_types=1);

namespace Warlock\Server\Service;

class Runner
{
    public static Config $config;
    // The Warlock protocol encoder/decoder.
    public static Protocol $protocol;

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
     * Task tags.
     *
     * @var array<Task>
     */
    private array $tags = [];

    /**
     * Application services.
     *
     * @var array<Task\Service>
     */
    private array $services = [];

    /**
     * @var array<string,int>
     */
    private array $stats = [
        'execs' => 0,           // The number of successful task executions
        'lateExecs' => 0,       // The number of delayed executions
        'failed' => 0,          // The number of failed task executions
        'tasks' => 0,           // The number of tasks in the queue
        'processes' => 0,       // The number of currently running processes
        'retries' => 0,         // The total number of task retries
        'limitHits' => 0,       // The number of hits on the process limiter
    ];

    public function run(): void
    {
        // Your code here
    }
}
