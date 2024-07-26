<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Exception;
use Hazaar\Application;
use Hazaar\File\Metric;
use Hazaar\File\RRD;
use Hazaar\Map;
use Hazaar\Warlock\Config;
use Hazaar\Warlock\Interfaces\Client as ClientInterface;
use Hazaar\Warlock\Protocol;

class Master
{
    /**
     * Singleton instance.
     */
    public static ?Master $instance = null;
    public static Config $config;

    /**
     * Main task queue.
     *
     * @var array<Task>
     */
    public array $tasks = [];

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
     * Enable silent mode.
     */
    private bool $silent = false;

    // Main loop state. On false, Warlock will exit the main loop and terminate
    private bool $running;
    private ?int $shutdown = null;

    /**
     * Task tags.
     *
     * @var array<Task>
     */
    private array $tags = [];

    /**
     * Epoch of when Warlock was started.
     */
    private int $start = 0;

    /**
     * Epoch of the last time stuff was processed.
     */
    private int $time = 0;

    /**
     * Current process id.
     */
    private int $pid = 0;

    /**
     * Current process id file.
     */
    private string $pidfile;

    /**
     * Default select() timeout.
     */
    private int $tv = 1;

    /**
     * @var array<string,int>
     */
    private $stats = [
        'clients' => 0,         // Total number of connected clients
        'processed' => 0,       // Total number of processed tasks & events
        'execs' => 0,           // The number of successful task executions
        'lateExecs' => 0,       // The number of delayed executions
        'failed' => 0,          // The number of failed task executions
        'tasks' => 0,           // The number of tasks in the queue
        'processes' => 0,       // The number of currently running processes
        'retries' => 0,         // The total number of task retries
        'limitHits' => 0,       // The number of hits on the process limiter
        'events' => 0,          // The number of events triggered
        'subscriptions' => 0,    // The number of waiting client connections
    ];
    private Metric $rrd;
    private Logger $log;

    /**
     * Application services.
     *
     * @var array<Task\Service>
     */
    private array $services = [];

    /**
     * The wait queue. Clients subscribe to events and are added to this array.
     *
     * @var array<string,array<mixed>>
     */
    private array $subscriptions = [];

    /**
     * The Event queue. Holds active events waiting to be seen.
     *
     * @var array<string,array<mixed>>
     */
    private array $events = [];

    /**
     * The global event queue.  Holds details about tasks that need to start up to process global events.
     *
     * @var array<string,callable>
     */
    private array $globals = [];

    /**
     * SOCKETS & STREAMS.
     */
    /**
     * The main socket for listening for incomming connections.
     *
     * @var resource
     */
    private $master;

    /**
     * Currently connected stream resources we are listening for data on.
     *
     * @var array<resource>
     */
    private $streams = [];

    /**
     * Currently connected clients.
     *
     * @var array<Client>
     */
    private $clients = [];

    /**
     * @var array<Client>
     */
    private array $admins = [];

    private ?KVStore $kvStore = null;

    /**
     * @var array<int,array<string,int|string>>
     */
    private array $exitCodes = [
        1 => [
            'lvl' => W_ERR,
            'msg' => 'Service failed to start because the application failed to decode the start payload.',
        ],
        2 => [
            'lvl' => W_ERR,
            'msg' => 'Service failed to start because the application runner does not understand the start payload type.',
        ],
        3 => [
            'lvl' => W_ERR,
            'msg' => 'Service failed to start because service class does not exist.',
        ],
        4 => [
            'lvl' => W_WARN,
            'msg' => 'Service exited because it lost the control channel.',
            'restart' => true,
        ],
        5 => [
            'lvl' => W_WARN,
            'msg' => 'Dynamic service failed to start because it has no runOnce() method!',
        ],
        6 => [
            'lvl' => W_INFO,
            'msg' => 'Service exited because it\'s source file was modified.',
            'restart' => true,
            'reset' => true,
        ],
        7 => [
            'lvl' => W_WARN,
            'msg' => 'Service exited due to an exception.',
            'restart' => true,
        ],
    ];

    /**
     * Warlock server constructor.
     *
     * The constructor here is responsible for setting up internal structures, initialising logging, RRD
     * logging, redirecting output to log files and configuring error and exception handling.
     *
     * @param bool $silent By default, log output will be displayed on the screen.  Silent mode will redirect all
     *                     log output to a file.
     */
    public function __construct(string $env = APPLICATION_ENV, bool $silent = false)
    {
        if (self::$instance) {
            throw new \Exception('Warlock is already running!');
        }
        self::$instance = $this;
        global $STDOUT;
        global $STDERR;
        $app = Application::getInstance();
        Application\Config::$overridePaths = ['host'.DIRECTORY_SEPARATOR.ake($_SERVER, 'SERVER_NAME'), 'local'];
        $this->silent = $silent;
        self::$config = new Config([], $env);
        if (!defined('RUNTIME_PATH')) {
            $path = APPLICATION_PATH.DIRECTORY_SEPARATOR.'.runtime';
            $appConfig = new Application\Config('application', APPLICATION_ENV);
            if ($appConfig->loaded() && $appConfig['app']->has('runtimePath')) {
                $path = $appConfig['app']['runtimePath'];
            }
            define('RUNTIME_PATH', $path);
        }
        self::$config->generateSystemID(APPLICATION_PATH);
        $runtime_path = $this->runtimePath(null, true);
        Logger::setDefaultLogLevel(self::$config['log']['level']);
        $this->log = new Logger();
        set_error_handler([$this, '__errorHandler']);
        set_exception_handler([$this, '__exceptionHandler']);
        if (!self::$config['sys']['phpBinary']) {
            self::$config['sys']['phpBinary'] = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'php';
        }
        if ($tz = self::$config['sys']->get('timezone')) {
            date_default_timezone_set($tz);
        }
        if ('0000' === self::$config['admin']['key']) {
            $msg = '* USING DEFAULT ADMIN KEY!!!  PLEASE CONSIDER SETTING server.key IN warlock config!!! *';
            $this->log->write(W_WARN, str_repeat('*', strlen($msg)));
            $this->log->write(W_WARN, $msg);
            $this->log->write(W_WARN, str_repeat('*', strlen($msg)));
        }
        if ($this->silent) {
            if (self::$config['log']['file']) {
                fclose(STDOUT);
                $STDOUT = fopen($runtime_path.DIRECTORY_SEPARATOR.self::$config['log']['file'], 'a');
            }
            if (self::$config['log']['error']) {
                fclose(STDERR);
                $STDERR = fopen($runtime_path.DIRECTORY_SEPARATOR.self::$config['log']['error'], 'a');
            }
        }
        $this->log->write(W_INFO, 'Warlock starting up...');
        $this->pid = getmypid();
        $this->pidfile = $runtime_path.DIRECTORY_SEPARATOR.self::$config['sys']->pid;
        $rrdfile = $runtime_path.DIRECTORY_SEPARATOR.self::$config['log']['rrd'];
        $this->rrd = new Metric($rrdfile);
        if (!$this->rrd->exists()) {
            $this->rrd->addDataSource('streams', 'GAUGE', 0, null, 'Stream Connections');
            $this->rrd->addDataSource('clients', 'GAUGE', 0, null, 'Clients');
            $this->rrd->addDataSource('memory', 'GAUGE', 0, null, 'Memory Usage');
            $this->rrd->addDataSource('tasks', 'COUNTER', 0, null, 'Task Executions');
            $this->rrd->addDataSource('events', 'COUNTER', 0, null, 'Events');
            $this->rrd->addDataSource('services', 'GAUGE', 0, null, 'Enabled Services');
            $this->rrd->addDataSource('processes', 'GAUGE', 0, null, 'Running Processes');
            $this->rrd->addArchive('permin_1hour', 'MAX', 1, 60, 'Max per minute for the last hour');
            $this->rrd->addArchive('perhour_100days', 'AVERAGE', 60, 2400, 'Average per hour for the last 100 days');
            $this->rrd->addArchive('perday_1year', 'AVERAGE', 1440, 365, 'Average per day for the last year');
            $this->rrd->create(60);
        }
        $this->log->write(W_NOTICE, 'PHP Version = '.PHP_VERSION);
        $this->log->write(W_NOTICE, 'PHP Binary = '.self::$config['sys']['phpBinary']);
        $this->log->write(W_NOTICE, 'Application path = '.APPLICATION_PATH);
        $this->log->write(W_NOTICE, 'Application name = '.self::$config['sys']['applicationName']);
        $this->log->write(W_NOTICE, 'Library path = '.LIBRARY_PATH);
        $this->log->write(W_NOTICE, 'Application environment = '.APPLICATION_ENV);
        $this->log->write(W_NOTICE, 'Runtime path = '.$runtime_path);
        $this->log->write(W_NOTICE, 'PID = '.$this->pid);
        $this->log->write(W_NOTICE, 'PID file = '.$this->pidfile);
        $this->log->write(W_NOTICE, 'Server ID = '.self::$config['sys']->id);
        $this->log->write(W_NOTICE, 'Listen address = '.self::$config['server']['listen']);
        $this->log->write(W_NOTICE, 'Listen port = '.self::$config['server']['port']);
        $this->log->write(W_NOTICE, 'Task expiry = '.self::$config['task']['expire'].' seconds');
        $this->log->write(W_NOTICE, 'Process timeout = '.self::$config['process']['timeout'].' seconds');
        $this->log->write(W_NOTICE, 'Process limit = '.self::$config['process']['limit'].' processes');
        Master::$protocol = new Protocol((string) self::$config['sys']['id'], self::$config['server']['encoded']);
        if (!self::$config['client']['applicationName']) {
            self::$config['client']['applicationName'] = self::$config['sys']['applicationName'];
        }
        if (true === self::$config['log']['rotate']) {
            $process = new Task\Internal([
                'when' => ake(self::$config['log'], 'rotateAt', '0 0 * * *'),
                'exec' => (object) [
                    'callable' => [$this, 'rotateLogFiles'],
                    'params' => [ake(self::$config['log'], 'logfiles')],
                ],
            ], self::$config);
            $this->taskQueueAdd($process);
        }
        if (true === self::$config['server']['announce']) {
            $task = new Task\Internal([
                'when' => '* * * * *',
                'exec' => (object) [
                    'callable' => [$this, 'announce'],
                ],
            ], self::$config);
            $this->taskQueueAdd($task);
        }
    }

    /**
     * Final cleanup of the PID file and logs the exit.
     */
    public function __destruct()
    {
        if (count($this->tasks) > 0) {
            $this->log->write(W_WARN, 'Killing processes with extreme prejudice!');
            foreach ($this->tasks as $task) {
                $task->terminate();
            }
        }
        if (file_exists($this->pidfile)) {
            unlink($this->pidfile);
        }
        $this->log->write(W_INFO, 'Exiting...');
    }

    final public function __errorHandler(
        int $errno,
        string $errstr,
        ?string $errfile = null,
        ?int $errline = null,
    ): ?bool {
        $type = match ($errno) {
            E_ERROR => W_ERR,
            E_WARNING => W_WARN,
            E_NOTICE => W_NOTICE,
            E_CORE_ERROR => W_ERR,
            E_CORE_WARNING => W_WARN,
            E_USER_ERROR => W_ERR,
            E_USER_WARNING => W_WARN,
            E_USER_NOTICE => W_NOTICE,
            default => W_ERR,
        };
        $this->log->write($type, "ERROR #{$errno} on line {$errline} of {$errfile} - {$errstr}");

        return true;
    }

    final public function __exceptionHandler(\Throwable $e): void
    {
        $code = $e->getCode();
        $this->log->write(W_ERR, 'EXCEPTION'.($code ? " #{$code}" : '')." at {$e->getFile()}({$e->getLine()})");
        echo $e->getMessage()."\n";

        if ($this->log->getLevel() >= W_DEBUG) {
            $trace = $e->getTrace();
            foreach ($trace as $i => $t) {
                echo "#{$i} {$t['file']}({$t['line']})\n";
            }
        }
    }

    private static function __signalHandler(int $signo, mixed $siginfo): void
    {
        self::$instance->log->write(W_DEBUG, 'Got signal: '.self::$instance->pcntlSignals[$signo]);

        switch ($signo) {
            case SIGHUP:
                if (false === self::$instance->loadConfig()) {
                    self::$instance->log->write(W_ERR, 'Reloading configuration failed!  Config disappeared?');
                }

                break;

            case SIGINT:
            case SIGTERM:
            case SIGQUIT:
                self::$instance->shutdown();

                break;
        }
    }

    public function loadConfig(): Config|false
    {
        $this->log->write(W_NOTICE, (self::$config->loaded() ? 'Re-l' : 'L').'oading configuration');
        $config = new Config();
        if (!$config->loaded()) {
            return false;
        }

        return self::$config = $config;
    }

    /**
     * Initiate a server shutdown.
     *
     * Because this server manages running services, it's not really a good idea to just simply exist abruptly. This
     * method will initiate a server shutdown which will nicely stop all services and once all services stop, the
     * server will terminate safely.
     *
     * @param int $delay how long in seconds before the shutdown should commence
     *
     * @return bool Returns true unless a shutdown has already been requested
     */
    public function shutdown(?int $delay = null): bool
    {
        if ($this->shutdown > 0) {
            return false;
        }
        if (null === $delay) {
            $delay = 0;
        }
        $this->log->write(W_DEBUG, "SHUTDOWN: DELAY={$delay}");
        $this->shutdown = time() + $delay;

        return true;
    }

    /**
     * Returns the application runtime directory.
     *
     * The runtime directory is a place where HazaarMVC will keep files that it needs to create during
     * normal operation. For example, cached views, and backend applications.
     *
     * @param mixed $suffix     An optional suffix to tack on the end of the path
     * @param mixed $create_dir if the runtime directory does not yet exist, try and create it (requires write permission)
     *
     * @return string The path to the runtime directory
     *
     * @throws \Exception
     */
    public function runtimePath($suffix = null, $create_dir = false)
    {
        $path = self::$config['sys']->get('runtimePath');
        if (!file_exists($path)) {
            $parent = dirname($path);
            if (!is_writable($parent)) {
                throw new \Exception('Not writable! Can not create runtime path: '.$path);
            }

            // Try and create the directory automatically
            try {
                mkdir($path, 0775);
            } catch (\Exception $e) {
                throw new \Exception('Error creating runtime path: '.$path);
            }
        }
        if (!is_writable($path)) {
            throw new \Exception('Runtime path not writable: '.$path);
        }
        $path = realpath($path);
        if ($suffix = trim((string) $suffix)) {
            if (DIRECTORY_SEPARATOR != substr($suffix, 0, 1)) {
                $suffix = DIRECTORY_SEPARATOR.$suffix;
            }
            $full_path = $path.$suffix;
            if (!file_exists($full_path) && $create_dir) {
                mkdir($full_path, 0775, true);
            }
        } else {
            $full_path = $path;
        }

        return $full_path;
    }

    /**
     * Prepares the server ready to get up and running.
     *
     * Bootstrapping the server allows us to restart an existing server instance without having to reinstantiate
     * it which allows the server to essentially restart itself in memory.
     *
     * @return Master Returns the server instance
     */
    public function bootstrap()
    {
        if ($this->isRunning()) {
            throw new \Exception('Warlock is already running.');
        }
        foreach ($this->pcntlSignals as $sig => $name) {
            pcntl_signal($sig, [$this, '__signalHandler'], true);
        }
        if (true === self::$config['kvstore']['enabled']) {
            $this->log->write(W_NOTICE, 'Initialising KV Store');
            $this->kvStore = new KVStore($this->log, self::$config['kvstore']['persist'], self::$config['kvstore']['compact']);
        }
        $this->log->write(W_NOTICE, 'Creating TCP socket stream on: '
            .self::$config['server']['listen'].':'.self::$config['server']['port']);
        if (!($this->master = stream_socket_server('tcp://'.self::$config['server']['listen'].':'.self::$config['server']['port'], $errno, $errstr))) {
            throw new \Exception($errstr, $errno);
        }
        $this->log->write(W_NOTICE, 'Configuring TCP socket');
        if (!stream_set_blocking($this->master, false)) {
            throw new \Exception('Failed: stream_set_blocking(0)');
        }
        $this->streams[0] = $this->master;
        $this->running = true;
        $services = new Application\Config('service', APPLICATION_ENV);
        if ($services->loaded()) {
            $this->log->write(W_INFO, 'Checking for enabled services');
            foreach ($services as $name => $options) {
                $this->log->write(W_NOTICE, "Found service: {$name}");
                $options = $options->toArray();
                $options['name'] = $name;
                $this->services[$name] = new Task\Service($options, self::$config);
                if (true === $options['enabled']) {
                    $this->serviceEnable($name);
                }
            }
        }
        if (self::$config->has('schedule')) {
            $this->log->write(W_NOTICE, 'Scheduling '.self::$config['schedule']->count().' tasks');
            foreach (self::$config['schedule'] as $task) {
                if (!$task->has('action')) {
                    $this->log->write(W_ERR, 'Warlock schedule config has no \'action\' attribute.');

                    continue;
                }
                $application = new Struct\Application([
                    'path' => APPLICATION_PATH,
                    'env' => APPLICATION_ENV,
                ]);
                if (!($callable = $this->callable(ake($task, 'action')))) {
                    $this->log->write(W_ERR, 'Warlock schedule config contains invalid callable.');

                    continue;
                }
                $exec = (object) ['callable' => $callable];
                if ($args = ake($task, 'args')) {
                    $exec->params = $args->toArray();
                }
                $when = ake($task, 'when');
                if ('@reboot' === strtolower($when)) {
                    $when = time() + self::$config['task']['boot_delay'];
                }
                $this->scheduleRunner($when, $exec, $application, ake($task, 'tag', uniqid()), ake($task, 'overwrite', false));
            }
        }
        if (self::$config->has('subscribe')) {
            $this->log->write(W_NOTICE, 'Found '.self::$config['subscribe']->count().' global events');
            foreach (self::$config['subscribe'] as $eventName => $eventFunc) {
                if (!($callable = $this->callable($eventFunc))) {
                    $this->log->write(W_ERR, 'Global event config contains invalid callable for event: '.$eventName);

                    continue;
                }
                $this->log->write(W_DEBUG, 'SUBSCRIBE: '.$eventName);
                $this->globals[$eventName] = $callable;
            }
        }
        $this->log->write(W_INFO, 'Ready...');

        return $this;
    }

    /**
     * The main server run loop.
     *
     * This method will not return for as long as the server is running.  While it is running it will
     * process tasks, monitor services and distribute server signals.
     *
     * @return int Returns an exit code indicating why the server is exiting. 0 means nice shutdown.
     */
    public function run()
    {
        $this->start = time();
        file_put_contents($this->pidfile, $this->pid);
        while ($this->running) {
            pcntl_signal_dispatch();
            if (null !== $this->shutdown && $this->shutdown <= time()) {
                $this->running = false;
            }
            if (!$this->running) {
                break;
            }
            $read = $this->streams;
            $write = $except = null;
            if (@stream_select($read, $write, $except, $this->tv) > 0) {
                $this->tv = 0;
                foreach ($read as $stream) {
                    if ($stream === $this->master) {
                        $client_socket = stream_socket_accept($stream);
                        if ($client_socket < 0) {
                            $this->log->write(W_ERR, 'Failed: socket_accept()');

                            continue;
                        }
                        $socket_id = (int) $client_socket;
                        $this->streams[$socket_id] = $client_socket;
                        $peer = stream_socket_get_name($client_socket, true);
                        $this->log->write(W_NOTICE, "Connection from {$peer} with socket id #{$socket_id}");
                    } else {
                        $this->clientProcess($stream);
                    }
                }
            } else {
                $this->tv = 1;
            }
            $now = time();
            if ($this->time < $now) {
                $this->taskProcess();
                $this->eventCleanup();
                $this->clientCheck();
                if (null !== $this->kvStore) {
                    $this->kvStore->expireKeys();
                }
                $this->rrd->setValue('streams', count($this->streams));
                $this->rrd->setValue('clients', count($this->clients));
                $this->rrd->setValue('tasks', count($this->tasks));
                $this->rrd->setValue('memory', memory_get_usage());
                if ($this->rrd->update()) {
                    gc_collect_cycles();
                }
                $this->time = $now;
            }
        }
        if (count($this->tasks) > 0) {
            $this->log->write(W_NOTICE, 'Terminating running tasks');
            foreach ($this->tasks as $task) {
                $task->cancel();
            }
            if (($wait = self::$config['process']['exitWait']) > 0) {
                $this->log->write(W_NOTICE, "Waiting for processes to exit (max {$wait} seconds)");
                $start = time();
                while ($this->stats['processes'] > 0) {
                    if (($start + $wait) < time()) {
                        $this->log->write(W_WARN, 'Timeout reached while waiting for process to exit.');

                        break;
                    }
                    if ($this->taskProcess() > 0) {
                        sleep(1);
                    }
                }
            }
        }
        $this->log->write(W_NOTICE, 'Closing all remaining connections');
        foreach ($this->streams as $stream) {
            fclose($stream);
        }
        $this->log->write(W_NOTICE, 'Cleaning up');
        $this->streams = [];
        $this->tasks = [];
        $this->clients = [];
        $this->events = [];
        $this->subscriptions = [];

        return 0;
    }

    public function authorise(Client $client, string $key): bool
    {
        if ($key !== self::$config['admin']['key']) {
            return false;
        }
        $this->log->write(W_NOTICE, 'Warlock control authorised to '.$client->id, $client->name);
        $client->type = 'admin';
        $this->admins[$client->id] = $client;

        return true;
    }

    /**
     * Removes a client from a stream.
     *
     * Because a client can have multiple stream connections (in legacy mode) this removes the client reference
     * for that stream. Once there are no more references left the client is completely removed.
     *
     * @param mixed $stream
     *
     * @return bool
     */
    public function clientRemove($stream)
    {
        if (!$stream) {
            return false;
        }
        $streamID = (int) $stream;
        if (!array_key_exists($streamID, $this->clients)) {
            return false;
        }
        $client = $this->clients[$streamID];
        foreach ($this->subscriptions as $eventID => &$queue) {
            if (!array_key_exists($client->id, $queue)) {
                continue;
            }
            $this->log->write(W_DEBUG, "CLIENT->UNSUBSCRIBE: EVENT={$eventID} CLIENT={$client->id}", $client->name);
            unset($queue[$client->id]);
        }
        $this->log->write(W_DEBUG, "CLIENT->REMOVE: CLIENT={$client->id}", $client->name);
        unset($this->clients[$streamID], $this->streams[$streamID]);

        --$this->stats['clients'];

        return true;
    }

    /**
     * @param resource $stream
     */
    public function disconnect(mixed $stream): bool
    {
        $streamID = (int) $stream;
        if ($client = $this->clientGet($stream)) {
            return $client->disconnect();
        }
        // Remove the stream from our list of streams
        if (array_key_exists($streamID, $this->streams)) {
            unset($this->streams[$streamID]);
        }
        $this->log->write(W_DEBUG, 'STREAM_CLOSE: STREAM='.$stream);
        stream_socket_shutdown($stream, STREAM_SHUT_RDWR);

        return fclose($stream);
    }

    /**
     * Process administative commands for a client.
     *
     * @param mixed $command
     * @param mixed $payload
     *
     * @return mixed
     */
    public function processCommand(Client $client, $command, &$payload)
    {
        if (null !== $this->kvStore && 'KV' === substr($command, 0, 2)) {
            return $this->kvStore->process($client, $command, $payload);
        }
        if (!($command && array_key_exists($client->id, $this->admins))) {
            throw new \Exception('Admin commands only allowed by authorised clients!');
        }
        $this->log->write(W_DEBUG, "ADMIN_COMMAND: {$command}".($client->id ? " CLIENT={$client->id}" : null));

        switch ($command) {
            case 'SHUTDOWN':
                $delay = ake($payload, 'delay', 0);
                $this->log->write(W_NOTICE, "Shutdown requested (Delay: {$delay})");
                if (!$this->shutdown($delay)) {
                    throw new \Exception('Unable to initiate shutdown!');
                }
                $client->send('OK', ['command' => $command]);

                break;

            case 'DELAY' :
                $payload->when = time() + ake($payload, 'value', 0);
                $this->log->write(W_DEBUG, "TASK->DELAY: INTERVAL={$payload->value}");

                // no break
            case 'SCHEDULE' :
                if (!property_exists($payload, 'when')) {
                    throw new \Exception('Unable schedule code execution without an execution time!');
                }
                if (!($id = $this->scheduleRunner(
                    $payload->when,
                    $payload->exec,
                    new Struct\Application($payload->application),
                    ake($payload, 'tag'),
                    ake($payload, 'overwrite', false)
                ))) {
                    throw new \Exception('Could not schedule delayed function');
                }
                $client->send('OK', ['command' => $command, 'task_id' => $id]);

                break;

            case 'CANCEL' :
                if (!$this->taskCancel($payload)) {
                    throw new \Exception('Error trying to cancel task');
                }
                $this->log->write(W_NOTICE, 'Task successfully cancelled');
                $client->send('OK', ['command' => $command, 'task_id' => $payload]);

                break;

            case 'ENABLE' :
                $this->log->write(W_NOTICE, "ENABLE: NAME={$payload} CLIENT={$client->id}");
                if (!$this->serviceEnable($payload)) {
                    throw new \Exception('Unable to enable service '.$payload);
                }
                $client->send('OK', ['command' => $command, 'name' => $payload]);

                break;

            case 'DISABLE' :
                $this->log->write(W_NOTICE, "DISABLE: NAME={$payload} CLIENT={$client->id}");
                if (!$this->serviceDisable($payload)) {
                    throw new \Exception('Unable to disable service '.$payload);
                }
                $client->send('OK', ['command' => $command, 'name' => $payload]);

                break;

            case 'STATUS':
                $this->log->write(W_NOTICE, "STATUS: CLIENT={$client->id}");
                $client->send('STATUS', $this->getStatus());

                break;

            case 'SERVICE' :
                $this->log->write(W_NOTICE, "SERVICE: NAME={$payload} CLIENT={$client->id}");
                if (!array_key_exists($payload, $this->services)) {
                    throw new \Exception('Service '.$payload.' does not exist!');
                }
                $client->send('SERVICE', $this->services[$payload]);

                break;

            case 'SPAWN':
                if (!($name = ake($payload, 'name'))) {
                    throw new \Exception('Unable to spawn a service without a service name!');
                }
                if (!($id = $this->spawn($client, $name, $payload))) {
                    throw new \Exception('Unable to spawn dynamic service: '.$name);
                }
                $client->send('OK', ['command' => $command, 'name' => $name, 'task_id' => $id]);

                break;

            case 'KILL':
                if (!($name = ake($payload, 'name'))) {
                    throw new \Exception('Can not kill dynamic service without a name!');
                }
                if (!$this->kill($client, $name)) {
                    throw new \Exception('Unable to kill dynamic service '.$name);
                }
                $client->send('OK', ['command' => $command, 'name' => $payload]);

                break;

            case 'SIGNAL':
                if (!($eventID = ake($payload, 'id'))) {
                    return false;
                }
                // Otherwise, send this signal to any child services for the requested type
                if (!($service = ake($payload, 'service'))) {
                    return false;
                }
                if (!$this->signal($client, $eventID, $service, ake($payload, 'data'))) {
                    throw new \Exception('Unable to signal dynamic service');
                }
                $client->send('OK', ['command' => $command, 'name' => $payload]);

                break;

            default:
                throw new \Exception('Unhandled command: '.$command);
        }

        return true;
    }

    public function trigger(string $eventID, mixed $data, ?string $clientID = null): bool
    {
        $this->log->write(W_NOTICE, "TRIGGER: {$eventID}");
        ++$this->stats['events'];
        $this->rrd->setValue('events', 1);
        $triggerID = uniqid();
        $seen = [];
        if ($clientID > 0) {
            $seen[] = $clientID;
        }
        $this->events[$eventID][$triggerID] = $payload = [
            'id' => $eventID,
            'trigger' => $triggerID,
            'when' => time(),
            'data' => $data,
            'seen' => $seen,
        ];
        if (array_key_exists($eventID, $this->globals)) {
            $this->log->write(W_NOTICE, 'Global event triggered', $eventID);
            $task = new Task\Runner([
                'exec' => $this->globals[$eventID],
                'params' => [$data, $payload],
                'timeout' => self::$config['process']['timeout'],
                'event' => true,
            ]);
            $this->taskQueueAdd($task);
            $this->taskProcess();
        }
        // Check to see if there are any clients waiting for this event and send notifications to them all.
        $this->subscriptionProcess($eventID, $triggerID);

        return true;
    }

    /**
     * Subscribe a client to an event.
     *
     * @param Client              $client  The client to subscribe
     * @param string              $eventID The event ID to subscribe to
     * @param array<string,mixed> $filter  Any event filters
     */
    public function subscribe(Client $client, string $eventID, ?array $filter = null): bool
    {
        $this->log->write(W_DEBUG, "CLIENT<-QUEUE: EVENT={$eventID} CLIENT={$client->id}", $client->name);
        $this->subscriptions[$eventID][$client->id] = [
            'client' => $client,
            'since' => time(),
            'filter' => $filter,
        ];
        if ($eventID === self::$config['admin']['trigger']) {
            $this->log->write(W_DEBUG, "ADMIN->SUBSCRIBE: CLIENT={$client->id}", $client->name);
        } else {
            ++$this->stats['subscriptions'];
        }
        // Check to see if this subscribe request has any active and unseen events waiting for it.
        $this->eventProcess($client, $eventID, $filter);

        return true;
    }

    /**
     * Unsubscibe a client from an event.
     *
     * @param Client $client  The client to unsubscribe
     * @param string $eventID The event ID to unsubscribe from
     */
    public function unsubscribe(Client $client, string $eventID): bool
    {
        if (!(array_key_exists($eventID, $this->subscriptions)
            && array_key_exists($client->id, $this->subscriptions[$eventID]))) {
            return false;
        }
        $this->log->write(W_DEBUG, "CLIENT<-DEQUEUE: NAME={$eventID} CLIENT={$client->id}", $client->name);
        unset($this->subscriptions[$eventID][$client->id]);
        if ($eventID === self::$config['admin']['trigger']) {
            $this->log->write(W_DEBUG, "ADMIN->UNSUBSCRIBE: CLIENT={$client->id}", $client->name);
        } else {
            --$this->stats['subscriptions'];
        }

        return true;
    }

    public function announce(): void
    {
        $this->log->write(
            W_INFO,
            'ANNOUNCE: STREAMS='.count($this->streams)
            .' CLIENTS='.count($this->clients)
            .' TASKS='.count($this->tasks)
            .' PROCESSES='.$this->stats['processes']
            .' SERVICES='.count($this->services)
            .' EVENTS='.count($this->events)
            .' SUBSCRIPTIONS='.count($this->subscriptions)
        );
    }

    /**
     * Check if the server is already running.
     *
     * This checks if the PID file exists, grabs the PID from that file and checks to see if a process with that ID
     * is actually running.
     *
     * @return bool True if the server is running. False otherwise.
     */
    private function isRunning(): bool
    {
        if (file_exists($this->pidfile)) {
            $pid = (int) file_get_contents($this->pidfile);
            $proc_file = '/proc/'.$pid.'/stat';
            if (file_exists($proc_file)) {
                $proc = file_get_contents($proc_file);

                return '' !== $proc && preg_match('/^'.preg_quote((string) $pid).'\s+\(php\)/', $proc);
            }
        }

        return false;
    }

    /**
     * @param resource $stream
     */
    private function clientAdd(mixed $stream): Client|false
    {
        // If we don't have a stream or id, return false
        if (!is_resource($stream)) {
            return false;
        }
        $streamID = (int) $stream;
        // If the stream is already has a client object, return it
        if (array_key_exists($streamID, $this->clients)) {
            return $this->clients[$streamID];
        }
        // Create the new client object
        $client = new Client($stream, self::$config['client']);
        // Add it to the client array
        $this->clients[$streamID] = $client;
        ++$this->stats['clients'];

        return $client;
    }

    /**
     * Retrieve a client object for a stream resource.
     *
     * @param resource $stream The stream resource
     */
    private function clientGet(mixed $stream): ?Client
    {
        $streamID = (int) $stream;

        return array_key_exists($streamID, $this->clients) ? $this->clients[$streamID] : null;
    }

    /**
     * @param resource $stream
     */
    private function clientProcess(mixed $stream): bool
    {
        $bytes_received = strlen($buf = fread($stream, 65535));
        $client = $this->clientGet($stream);
        if ($client instanceof ClientInterface) {
            if ('client' === $client->type) {
                if (0 === $bytes_received) {
                    $this->log->write(W_NOTICE, "Remote host {$client->address}:{$client->port} closed connection", $client->name);
                    $this->disconnect($stream);

                    return false;
                }
                $this->log->write(W_DEBUG, "CLIENT<-RECV: HOST={$client->address} PORT={$client->port} BYTES=".$bytes_received, $client->name);
            }
            $client->recv($buf);
        } else {
            if (!($client = $this->clientAdd($stream))) {
                $this->disconnect($stream);

                return false;
            }

            if (!$client->initiateHandshake($buf)) {
                $this->clientRemove($stream);
                stream_socket_shutdown($stream, STREAM_SHUT_RDWR);
            }
        }

        return true;
    }

    private function clientCheck(): void
    {
        if (!(self::$config['client']['check'] > 0 && is_array($this->clients) && count($this->clients) > 0)) {
            return;
        }
        // Only ping if we havn't received data from the client for the configured number of seconds (default to 60).
        $when = time() - self::$config['client']['check'];
        foreach ($this->clients as $client) {
            if (!$client instanceof Client) {
                continue;
            }
            if ($client->lastContact <= $when) {
                $client->ping();
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private function getStatus(bool $full = true): array
    {
        $status = [
            'state' => 'running',
            'pid' => $this->pid,
            'started' => $this->start,
            'uptime' => time() - $this->start,
            'memory' => memory_get_usage(),
            'stats' => $this->stats,
            'connections' => count($this->streams),
            'clients' => count($this->clients),
        ];
        if (!$full) {
            return $status;
        }
        $status['clients'] = [];
        $status['tasks'] = [];
        $status['processes'] = [];
        $status['services'] = [];
        $status['events'] = [];
        $status['stats'] = $this->stats;
        foreach ($this->clients as $client) {
            $status['clients'][] = [
                'id' => $client->id,
                'username' => $client->username,
                'since' => $client->since,
                'ip' => $client->address,
                'port' => $client->port,
                'type' => $client->type,
            ];
        }
        $arrays = [
            'tasks' => $this->tasks,             // Main task queue
            'services' => $this->services,          // Configured services
            'events' => $this->events,           // Event queue
        ];
        foreach ($arrays as $name => &$array) {
            $status['stats'][$name] = count($array);
            if ('events' === $name && array_key_exists(self::$config['admin']['trigger'], $array)) {
                $status[$name] = array_diff_key($array, array_flip([
                    self::$config['admin']['trigger'],
                ]));
            } else {
                $status[$name] = $array;
            }
        }

        return $status;
    }

    /**
     * @param array<mixed> $options
     */
    private function spawn(Client $client, string $name, array $options): false|string
    {
        if (!array_key_exists($name, $this->services)) {
            return false;
        }
        $service = &$this->services[$name];
        $application = new Struct\Application();
        $task = new Task\Service([
            'name' => $name,
            'start' => time(),
            'application' => $application,
            'tag' => $name,
            'enabled' => true,
            'dynamic' => true,
            'detach' => ake($options, 'detach', false),
            'respawn' => false,
            'client' => $client,
            'params' => ake($options, 'params'),
            'loglevel' => $service->loglevel,
        ]);
        $this->log->write(W_NOTICE, 'Spawning dynamic service: '.$name, $task->id);
        $this->taskQueueAdd($task);

        return $task->id;
    }

    private function kill(Client $client, string $name): bool
    {
        if (!array_key_exists($name, $this->services)) {
            return false;
        }
        foreach ($client->tasks as $id => $task) {
            if ($task->name !== $name) {
                continue;
            }
            $this->log->write(W_NOTICE, "KILL: SERVICE={$name} TASK_ID={$id} CLIENT={$client->id}");
            $task->cancel();
            unset($client->tasks[$id]);
        }

        return true;
    }

    private function signal(Client $client, string $eventID, string $service, mixed $data = null): bool
    {
        $triggerID = uniqid();
        // If this is a message coming from the service, send it back to it's parent client connection
        if ('service' === $client->type) {
            if (0 === count($client->tasks)) {
                throw new \Exception('Client has no associated tasks!');
            }
            foreach ($client->tasks as $id => $task) {
                if (!(array_key_exists($id, $this->tasks) && $task->name === $service && $task->client instanceof Client)) {
                    continue;
                }
                $this->log->write(W_NOTICE, "SERVICE->SIGNAL: SERVICE={$service} TASK_ID={$id} CLIENT={$client->id}");
                $task->client->sendEvent($eventID, $triggerID, $data);
            }
        } else {
            if (0 === count($client->tasks)) {
                throw new \Exception('Client has no associated tasks!');
            }
            foreach ($client->tasks as $id => $task) {
                if (!(array_key_exists($id, $this->tasks) && $task->name === $service)) {
                    continue;
                }
                $this->log->write(W_NOTICE, "CLIENT->SIGNAL: SERVICE={$service} TASK_ID={$id} CLIENT={$client->id}");
                $task->sendEvent($eventID, $triggerID, $data);
            }
        }

        return true;
    }

    private function scheduleRunner(
        int $start,
        \stdClass $exec,
        Struct\Application $application,
        ?string $tag = null,
        bool $overwrite = false
    ): false|string {
        if (!property_exists($exec, 'callable')) {
            $this->log->write(W_ERR, 'Unable to schedule task without function callable!');

            return false;
        }
        if (null === $tag && is_array($exec->callable)) {
            $tag = md5(implode('-', $exec->callable));
            $overwrite = true;
        }
        if ($tag && array_key_exists($tag, $this->tags)) {
            $task = $this->tags[$tag];
            $this->log->write(W_NOTICE, "Task already scheduled with tag {$tag}", $task->id);
            if (false === $overwrite) {
                $this->log->write(W_NOTICE, 'Skipping', $task->id);

                return false;
            }
            $this->log->write(W_NOTICE, 'Overwriting', $task->id);
            $task->cancel();
            unset($this->tags[$tag], $this->tasks[$task->id]);
        }
        $task = new Task\Runner([
            'start' => $start,
            'application' => $application,
            'exec' => $exec->callable,
            'params' => ake($exec, 'params', []),
            'timeout' => self::$config['process']['timeout'],
        ]);
        $this->log->write(W_DEBUG, "TASK: ID={$task->id}");
        $this->log->write(W_DEBUG, 'WHEN: '.date(self::$config['sys']['dateFormat'], $task->start), $task->id);
        $this->log->write(W_DEBUG, 'APPLICATION_ENV: '.$application->env, $task->id);
        if ($tag) {
            $this->log->write(W_DEBUG, 'TAG: '.$tag, $task->id);
            $this->tags[$tag] = $task;
        }
        $this->log->write(W_NOTICE, 'Scheduling task for execution at '.date(self::$config['sys']['dateFormat'], $start), $task->id);
        $this->taskQueueAdd($task);

        return $task->id;
    }

    private function taskCancel(string $taskID): bool
    {
        $this->log->write(W_DEBUG, 'Trying to cancel task', $taskID);
        // If the task IS is not found return false
        if (!array_key_exists($taskID, $this->tasks)) {
            return false;
        }
        $task = &$this->tasks[$taskID];
        if ($task->tag) {
            unset($this->tags[$task->tag]);
        }
        // Stop the task if it is currently running
        if (TASK_RUNNING === $task->status && $task->isRunning()) {
            $this->log->write(W_NOTICE, 'Stopping running '.$task->type);
            $task->terminate();
        }
        $task->status = TASK_CANCELLED;
        // Expire the task in 30 seconds
        $task->expire = time() + self::$config['task']['expire'];

        return true;
    }

    /**
     * Main process loop.
     *
     * This method will monitor and manage queued running tasks.
     */
    private function taskProcess(): int
    {
        $processes = 0;
        foreach ($this->tasks as $id => &$task) {
            switch ($task->status) {
                case TASK_QUEUED:
                case TASK_RESTART:
                case TASK_RETRY: // Tasks that are queued and ready to execute or ready to restart an execution retry.
                    if ($task->ready()) {
                        if ($this->stats['processes'] >= self::$config['process']['limit']) {
                            ++$this->stats['limitHits'];
                            $this->log->write(W_WARN, 'Process limit of '.self::$config['process']['limit'].' processes reached!');

                            break;
                        }
                        $task->start();
                        $this->rrd->setValue('tasks', 1);
                    }

                    if (TASK_STARTING !== $task->status) {
                        break;
                    }

                    // no break
                case TASK_STARTING:
                    $task->run();
                    $pipe = $task->getReadPipe();
                    $pipeID = (int) $pipe;
                    $this->streams[$pipeID] = $pipe;
                    $task->client = new Client\Service($task->getWritePipe(), self::$config['client']);
                    $task->client->type = 'service';
                    $this->clients[$pipeID] = $task->client;

                    break;

                case TASK_CANCELLED:
                    if ($task->expired()) {
                        $task->terminate();
                        if (true === $this->running) {
                            $task->status = TASK_WAIT;
                        }

                        break;
                    }

                    // no break
                case TASK_RUNNING:
                    $processes++;
                    $this->taskMonitor($task);
                    if ($task->timeout()) {
                        $this->log->write(W_WARN, 'Process taking too long to execute - Attempting to kill it.', $id);
                        if ($task->terminate()) {
                            $this->log->write(W_DEBUG, 'Terminate signal sent.', $id);
                        } else {
                            $this->log->write(W_ERR, 'Failed to send terminate signal.', $id);
                        }
                    }

                    break;

                case TASK_COMPLETE:
                    if (($next = $task->touch()) > time() && true === $this->running) {
                        $task->status = TASK_QUEUED;
                        $task->retries = 0;
                        $this->log->write(W_NOTICE, 'Next execution at: '.date(self::$config['sys']['dateFormat'], $next), $task->id);
                    } else {
                        $task->status = TASK_WAIT;
                        // Expire the task in 30 seconds
                        $task->expire = time() + self::$config['task']['expire'];
                    }

                    break;

                case TASK_ERROR:
                    if ($task->retries < self::$config['task']['retries']) {
                        $this->log->write(W_NOTICE, 'Task failed. Retrying in '.self::$config['task']['retry'].' seconds', $task->id);
                        $task->status = TASK_RETRY;
                        ++$this->stats['retries'];
                    } else {
                        $this->log->write(W_ERR, 'Task execution failed', $id);
                        $task->status = TASK_WAIT;
                        $task->expire = time() + self::$config['task']['expire'];
                    }

                    break;

                case TASK_WAIT:
                    if ($task->expired()) {
                        $this->log->write(W_NOTICE, 'Cleaning up', $id);
                        --$this->stats['tasks'];
                        unset($this->tasks[$id]);
                    }
            }
        }

        return $this->stats['processes'] = $processes;
    }

    private function taskMonitor(Task $task): void
    {
        // $this->log->write(W_DEBUG, 'PROCESS->RUNNING: PID='.$task->pid.' ID='.$task->id);
        $status = $task->procStatus;
        if (false === $status) {
            return;
        }
        if (true === $status['running']) {
            try {
                // Receive any error from STDERR
                if (($output = $task->readErrorPipe()) !== false) {
                    $this->log->write(W_ERR, "PROCESS ERROR:\n{$output}");
                }
            } catch (\Throwable $e) {
                $this->log->write(W_ERR, 'EXCEPTION #'
                    .$e->getCode()
                    .' on line '.$e->getLine()
                    .' in file '.$e->getFile()
                    .': '.$e->getMessage());
            }
        } else {
            $this->log->write(W_DEBUG, "PROCESS->STOP: PID={$status['pid']} ID=".$task->id);
            $pipe = $task->getReadPipe();
            if ($client = $this->clientGet($pipe)) {
                // Do any last second processing.  Usually shutdown log messages.
                if ($buffer = stream_get_contents($pipe)) {
                    $client->recv($buffer);
                }
            }
            // One last check of the error buffer
            if (($output = $task->readErrorPipe()) !== false) {
                $this->log->write(W_ERR, "PROCESS ERROR:\n{$output}");
            }
            $task->close();
            $this->clientRemove($pipe);
            $task->client = null;
            // Process a Service shutdown.
            if ($task instanceof Task\Service) {
                $name = $task->name;
                $this->log->write(W_DEBUG, "SERVICE={$name} EXIT={$status['exitcode']}");
                if (0 !== $status['exitcode'] && TASK_CANCELLED !== $task->status) {
                    $this->log->write(W_NOTICE, "Service returned status code {$status['exitcode']}", $name);
                    if (!($ec = ake($this->exitCodes, $status['exitcode']))) {
                        $ec = [
                            'lvl' => W_WARN,
                            'msg' => 'Service exited unexpectedly.',
                            'restart' => true,
                        ];
                    }
                    $this->log->write($ec['lvl'], $ec['msg'], $name);
                    if (true !== ake($ec, 'restart', false)) {
                        $this->log->write(W_ERR, 'Disabling the service.', $name);
                        $task->status = TASK_ERROR;

                        // break;
                    }
                    if (true === ake($ec, 'reset', false)) {
                        $task->retries = 0;
                    }
                    if ($task->retries > self::$config['service']['restarts']) {
                        if (true === $task->dynamic) {
                            $this->log->write(W_WARN, 'Dynamic service is restarting too often.  Cancelling spawn.', $name);
                            $this->taskCancel($task->id);
                        } else {
                            $this->log->write(W_WARN, 'Service is restarting too often.  Disabling for '.self::$config['service']['disable'].' seconds.', $name);
                            $task->start = time() + self::$config['service']['disable'];
                            $task->retries = 0;
                            $task->expire = 0;
                        }
                    } else {
                        $this->log->write(W_NOTICE, 'Restarting service'
                            .(($task->retries > 0) ? " ({$task->retries})" : null), $name);
                        if (array_key_exists($task->name, $this->services)) {
                            ++$this->services[$task->name]->restarts;
                        }
                        $task->status = TASK_RESTART;
                    }
                } elseif (true === $task->respawn && TASK_RUNNING === $task->status) {
                    $this->log->write(W_NOTICE, 'Respawning service in '
                        .$task->respawn_delay.' seconds.', $name);
                    $task->start = time() + $task->respawn_delay;
                    $task->status = TASK_QUEUED;
                    if (array_key_exists($task->name, $this->services)) {
                        ++$this->services[$task->name]->restarts;
                    }
                } else {
                    $task->status = TASK_COMPLETE;
                    $task->expire = time();
                }
            } else {
                $this->log->write(W_NOTICE, 'Process exited with return code: '.$status['exitcode'], $task->id);
                if ($status['exitcode'] > 0) {
                    $this->log->write(W_WARN, 'Execution completed with error.', $task->id);
                    if (true === $task->event) {
                        $task->status = TASK_ERROR;
                    } elseif ($task->retries >= self::$config['task']['retries']) {
                        $this->log->write(W_ERR, 'Cancelling task due to too many retries.', $task->id);
                        $task->status = TASK_ERROR;
                        ++$this->stats['failed'];
                    } else {
                        $this->log->write(W_NOTICE, 'Re-queuing task for execution.', $task->id);
                        $task->status = TASK_RETRY;
                        $task->start = time() + self::$config['task']['retry'];
                        ++$task->retries;
                    }
                } else {
                    $this->log->write(W_NOTICE, 'Execution completed successfully.', $task->id);
                    ++$this->stats['execs'];
                    $task->status = TASK_COMPLETE;
                }
            }
        }
    }

    private function taskQueueAdd(Task $task): void
    {
        if (array_key_exists($task->id, $this->tasks)) {
            $this->log->write(W_WARN, 'Process already exists in queue!', $task->id);

            return;
        }
        $task->application = new Struct\Application();
        $this->tasks[$task->id] = $task;
        ++$this->stats['tasks'];
        $this->admins[$task->id] = $task; // Make all processes admins so they can issue delay/schedule/etc.
        $this->log->write(W_DEBUG, 'TASK->QUEUE: START='
            .date(self::$config['sys']['dateFormat'], $task->start)
            .($task->tag ? " TAG={$task->tag}" : ''), $task->id);
        $this->log->write(W_DEBUG, 'APPLICATION_PATH: '.$task->application->path, $task->id);
        $this->log->write(W_DEBUG, 'APPLICATION_ENV:  '.$task->application->env, $task->id);
        $task->status = TASK_QUEUED;
    }

    private function eventCleanup(): void
    {
        if (!is_array($this->events)) {
            $this->events = [];
        }
        if (false === self::$config['sys']['cleanup']) {
            return;
        }
        if (count($this->events) > 0) {
            foreach ($this->events as $eventID => $events) {
                foreach ($events as $id => $data) {
                    if ((int) ($data['when'] + self::$config['event']['queueTimeout']) <= time()) {
                        if ($eventID != self::$config['admin']['trigger']) {
                            $this->log->write(W_DEBUG, "EXPIRE: NAME={$eventID} TRIGGER={$id}");
                        }
                        unset($this->events[$eventID][$id]);
                    }
                }
                if (0 === count($this->events[$eventID])) {
                    unset($this->events[$eventID]);
                }
            }
        }
    }

    /**
     * @param array<mixed> $search
     */
    private function fieldExists(array $search, \stdClass $array): bool
    {
        reset($search);
        while ($field = current($search)) {
            if (!property_exists($array, $field)) {
                return false;
            }
            $array = &$array->{$field};
            next($search);
        }

        return true;
    }

    /**
     * @param array<mixed> $search
     */
    private function getFieldValue(array $search, \stdClass $array): mixed
    {
        reset($search);
        while ($field = current($search)) {
            if (!property_exists($array, $field)) {
                return false;
            }
            $array = &$array->{$field};
            next($search);
        }

        return $array;
    }

    /**
     * Tests whether a event should be filtered.
     *
     * Returns true if the event should be filtered (skipped), and false if the event should be processed.
     *
     * @param mixed        $event  the event to check
     * @param array<mixed> $filter the filter rule to test against
     *
     * @return bool returns true if the event should be filtered (skipped), and false if the event should be processed
     */
    private function eventFilter(mixed $event, ?array $filter = null): bool
    {
        $this->log->write(W_DEBUG, 'Checking event filter for \''.$event['id'].'\'');
        foreach ($filter as $field => $data) {
            $field = explode('.', $field);
            if (!$this->fieldExists($field, $event['data'])) {
                return false;
            }
            $field_value = $this->getFieldValue($field, $event['data']);
            if ($data instanceof \stdClass) { // If $data is an array it's a complex filter
                foreach (get_object_vars($data) as $filter_type => $filter_value) {
                    switch ($filter_type) {
                        case 'is':
                            if ($field_value != $filter_value) {
                                return true;
                            }

                            break;

                        case 'not':
                            if ($field_value === $filter_value) {
                                return true;
                            }

                            break;

                        case 'like':
                            if (!preg_match($filter_value, $field_value)) {
                                return true;
                            }

                            break;

                        case 'in':
                            if (!in_array($field_value, $filter_value)) {
                                return true;
                            }

                            break;

                        case 'nin':
                            if (in_array($field_value, $filter_value)) {
                                return true;
                            }

                            break;
                    }
                }
            } else { // Otherwise it's a simple filter with an acceptable value in it
                if ($field_value != $data) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Process the event queue for a specified client.
     *
     * This method is executed when a client connects to see if there are any events waiting in the event
     * queue that the client has not yet seen.  If there are, the first event found is sent to the client, marked
     * as seen and then processing stops.
     *
     * @param array<mixed> $filter
     */
    private function eventProcess(Client $client, string $eventID, ?array $filter = null): bool
    {
        if (!(array_key_exists($eventID, $this->events)
            && ($count = count($this->events[$eventID])) > 0)) {
            return false;
        }
        $this->log->write(W_DEBUG, "QUEUE: EVENT={$eventID} COUNT={$count}");
        foreach ($this->events[$eventID] as $triggerID => &$event) {
            if (!array_key_exists('seen', $event) || !is_array($event['seen'])) {
                $event['seen'] = [];
            }
            if (!in_array($client->id, $event['seen'])) {
                if (!array_key_exists($eventID, $client->subscriptions)) {
                    continue;
                }
                if ($this->eventFilter($event, $filter)) {
                    continue;
                }
                $this->log->write(W_NOTICE, "Sending event '{$event['id']}' to {$client->id}");
                if (!$client->sendEvent($event['id'], $triggerID, $event['data'])) {
                    return false;
                }
                $event['seen'][] = $client->id;
                if ($eventID != self::$config['admin']['trigger']) {
                    $this->log->write(W_DEBUG, "SEEN: NAME={$eventID} TRIGGER={$triggerID} CLIENT=".$client->id);
                }
            }
        }

        return true;
    }

    /**
     * Process all subscriptions for a specified event.
     *
     * This method is executed when a event is triggered.  It is responsible for sending events to clients
     * that are waiting for the event and marking them as seen by the client.
     */
    private function subscriptionProcess(string $eventID, ?string $triggerID = null): bool
    {
        if (!(array_key_exists($eventID, $this->events)
            && ($count = count($this->events[$eventID])) > 0)) {
            return false;
        }
        $this->log->write(W_DEBUG, "QUEUE: NAME={$eventID} COUNT={$count}");
        // Get a list of triggers to process
        $triggers = (empty($triggerID) ? array_keys($this->events[$eventID]) : [$triggerID]);
        foreach ($triggers as $trigger) {
            if (!isset($this->events[$eventID][$trigger])) {
                continue;
            }
            $event = &$this->events[$eventID][$trigger];
            if (!array_key_exists($eventID, $this->subscriptions)) {
                continue;
            }
            foreach ($this->subscriptions[$eventID] as $clientID => $item) {
                if (in_array($clientID, $event['seen'])
                    || (is_array($item['filter']) && $this->eventFilter($event, $item['filter']))) {
                    continue;
                }
                $this->log->write(W_NOTICE, "Sending event '{$event['id']}' to {$clientID}");
                if (!$item['client']->sendEvent($eventID, $trigger, $event['data'])) {
                    return false;
                }
                $event['seen'][] = $clientID;
                if ($eventID != self::$config['admin']['trigger']) {
                    $this->log->write(W_DEBUG, "SEEN: NAME={$eventID} TRIGGER={$trigger} CLIENT={$clientID}");
                }
            }
        }

        return true;
    }

    private function serviceEnable(string $name): bool
    {
        if (!array_key_exists($name, $this->services)) {
            return false;
        }
        $service = $this->services[$name];
        $this->log->write(W_INFO, 'Enabling service: '.$name.(($service->delay > 0) ? ' (delay='.$service->delay.')' : null));
        $service->enabled = true;
        if ($service->delay > 0) {
            $service->start = time() + $service->delay;
        }
        $this->taskQueueAdd($service);

        return true;
    }

    private function serviceDisable(string $name): bool
    {
        if (!array_key_exists($name, $this->services)) {
            return false;
        }
        $service = &$this->services[$name];
        if (!$service->enabled) {
            return false;
        }
        $this->log->write(W_INFO, 'Disabling service: '.$name);

        return $service->disable(self::$config['task']['expire']);
    }

    /**
     * @return null|array<mixed>
     */
    private function callable(mixed $value): ?array
    {
        if ($value instanceof Map) {
            $value = $value->toArray();
        }
        if (is_array($value)) {
            return $value;
        }
        if (false !== strpos($value, '::')) {
            return explode('::', $value, 2);
        }
        if (false !== strpos($value, '->')) {
            return explode('->', $value, 2);
        }

        return null;
    }

    private function rotateLogFiles(int $logfiles = 0): bool
    {
        if (!$this->silent) {
            return false;
        }
        global $STDOUT;
        global $STDERR;
        $this->log->write(W_NOTICE, "ROTATING LOG FILES: MAX={$logfiles}");
        $runtime_path = rtrim(self::$config['sys']->runtimePath);
        if (!\is_dir($runtime_path)) {
            return false;
        }
        if (self::$config['log']['file']) {
            $out = $runtime_path.DIRECTORY_SEPARATOR.self::$config['log']['file'];
            fclose($STDOUT);
            rotateLogFile($out, $logfiles);
            $STDOUT = fopen($out, 'a');
        }
        if (self::$config['log']['error']) {
            $err = $runtime_path.DIRECTORY_SEPARATOR.self::$config['log']['error'];
            fclose($STDERR);
            rotateLogFile($err, $logfiles);
            $STDERR = fopen($err, 'a');
        }

        return true;
    }
}
