<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\Application\Config;
use Hazaar\Application\Protocol;
use Hazaar\Warlock\Exception\ExtensionNotLoaded;
use Hazaar\Warlock\Server\Component\Cluster;
use Hazaar\Warlock\Server\Component\Logger;
use Hazaar\Warlock\Server\Enum\LogLevel;
use parallel\Channel;

class Main
{
    /**
     * @var array<string, mixed>
     */
    public static array $defaultConfig = [
        'serverId' => 1,
        'listenAddress' => '0.0.0.0',
        'port' => 13080,
        'encode' => false,
        'phpBinary' => PHP_BINARY,
        'log' => [
            'level' => 'debug',
        ],
        'timezone' => 'UTC',
        'client' => [
            'check' => 60,
        ],
    ];

    /**
     * Signals that we will capture.
     *
     * @var array<int, string>
     */
    public array $pcntlSignals = [
        SIGINT => 'SIGINT',
        SIGTERM => 'SIGTERM',
        SIGQUIT => 'SIGQUIT',
    ];

    public static self $instance;
    public Protocol $protocol;

    private bool $silent = false;

    /**
     * Epoch of the last time stuff was processed.
     */
    private int $time = 0;

    /**
     * @var array<string, Channel>
     */
    private array $channels = [];
    private Config $config;
    private Logger $log;

    /**
     * @var bool indicates whether the server is currently running
     */
    private bool $running = true;
    private ?int $shutdown = null;

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
     * Default select() timeout.
     */
    private int $tv = 1;

    public function __construct(string $configFile = 'warlock', string $env = 'development')
    {
        if (!extension_loaded('sockets')) {
            throw new ExtensionNotLoaded('sockets');
        }
        if (!extension_loaded('pcntl')) {
            throw new ExtensionNotLoaded('pcntl');
        }
        self::$instance = $this;
        $this->config = Config::getInstance(sourceFile: $configFile, env: $env, defaults: self::$defaultConfig);
        $this->log = new Logger(level: $this->config['log']['level']);
        $this->protocol = new Protocol($this->config['serverId'], $this->config['encode']);
        if ($tz = $this->config['timezone']) {
            date_default_timezone_set(timezoneId: $tz);
        }
    }

    private static function __signalHandler(int $signo, mixed $siginfo): void
    {
        self::$instance->log->write('SIGNAL: '.self::$instance->pcntlSignals[$signo], LogLevel::DEBUG);

        switch ($signo) {
            case SIGINT:
            case SIGTERM:
            case SIGQUIT:
                self::$instance->shutdown();

                break;
        }
    }

    public function bootstrap(): self
    {
        if (!class_exists('parallel\Runtime')) {
            throw new \Exception('The parallel extension is required to run the Warlock server!');
        }
        $this->writeStartupMessage('Warlock starting up...');
        foreach ($this->pcntlSignals as $sig => $name) {
            pcntl_signal($sig, [$this, '__signalHandler'], true);
        }
        // $this->pid = getmypid();
        // $this->pidfile = $runtimePath.DIRECTORY_SEPARATOR.$this->config['sys']->pid;
        // $this->channels['test'] = Channel::make('test', Channel::Infinite);
        // $this->channels['log'] = Channel::make('log', Channel::Infinite);
        $this->log->write('Creating TCP socket stream on: '.$this->config['listenAddress'].':'.$this->config['port'], LogLevel::NOTICE);
        if (!($this->master = stream_socket_server('tcp://'.$this->config['listenAddress'].':'.$this->config['port'], $errno, $errstr))) {
            throw new \Exception($errstr, $errno);
        }
        $this->log->write('Configuring TCP socket', LogLevel::NOTICE);
        if (!stream_set_blocking($this->master, false)) {
            throw new \Exception('Failed: stream_set_blocking(0)');
        }
        $this->streams[0] = $this->master;
        $this->running = true;
        // if ($this->config['cluster']['enabled'] ?? false) {
        //     $this->cluster = new Cluster($this->log, $this->config['cluster']);
        // }

        $this->log->write('Ready...', LogLevel::INFO);

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
        // $this->cluster->start();
        while ($this->running) {
            pcntl_signal_dispatch();
            if (null !== $this->shutdown && $this->shutdown <= time()) {
                $this->running = false;
            }
            if (!$this->running) {
                break;
            }
            $this->socketSelect();
            $now = time();
            if ($this->time < $now) {
                // $this->cluster->process();
                $this->eventCleanup();
                $this->clientCheck();
                $this->time = $now;
            }
        }
        // $this->cluster->stop();
        $this->log->write('Closing all remaining connections', LogLevel::NOTICE);
        foreach ($this->streams as $stream) {
            fclose($stream);
        }
        $this->log->write('Cleaning up', LogLevel::NOTICE);
        $this->streams = [];
        $this->clients = [];
        $this->events = [];
        $this->subscriptions = [];

        return 0;
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
        $this->log->write("SHUTDOWN: DELAY={$delay}", LogLevel::DEBUG);
        $this->shutdown = time() + $delay;

        return true;
    }

    public function testrun(): int
    {
        $runner = function (int $id): int {
            require_once __DIR__.'/Service/Test.php';
            $test = new Components\Test();

            return $test->run($id);
        };
        $futures[] = \parallel\run($runner, [1]);
        $running = true;
        while ($running) {
            foreach ($futures as $index => $future) {
                if ($future->done()) {
                    echo "{$index} returned: ".$future->value().PHP_EOL;
                    unset($futures[$index]);
                }
            }
            if (empty($futures)) {
                $running = false;
            } else {
                // echo 'Waiting...'.PHP_EOL;
                echo 'Sending event to test channel'.PHP_EOL;
                $this->channels['test']->send(['text', uniqid()]);
                sleep(1);
            }
        }

        return 0;
    }

    public function setSilent(bool $silent): void
    {
        $this->silent = $silent;
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
        $this->log->write('STREAM_CLOSE: STREAM='.$stream, LogLevel::DEBUG);
        stream_socket_shutdown($stream, STREAM_SHUT_RDWR);

        return fclose($stream);
    }

    /**
     * @param resource $stream
     */
    public function clientAdd(mixed $stream, ?Client $client = null): Client|false
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
        if (null === $client) {
            $client = new Client($stream, $this->config['client']);
        }
        $this->log->write("CLIENT->ADD: CLIENT={$client->id}", LogLevel::DEBUG);
        // Add it to the client array
        $this->clients[$streamID] = $client;
        if (!array_key_exists($streamID, $this->streams)) {
            $this->streams[$streamID] = $stream;
        }

        return $client;
    }

    public function clientReplace(mixed $stream, ?Client $client = null): bool
    {
        $streamID = (int) $stream;
        if (!array_key_exists($streamID, $this->clients)) {
            return false;
        }
        $this->clients[$streamID] = $client;

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
            $this->log->write("CLIENT->UNSUBSCRIBE: EVENT={$eventID} CLIENT={$client->id}", LogLevel::DEBUG);
            unset($queue[$client->id]);
        }
        $this->log->write("CLIENT->REMOVE: CLIENT={$client->id}", LogLevel::DEBUG);
        unset($this->clients[$streamID], $this->streams[$streamID]);

        return true;
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
                $delay = $payload['delay'] ?? 0;
                $this->log->write(W_NOTICE, "Shutdown requested (Delay: {$delay})");
                if (!$this->shutdown($delay)) {
                    throw new \Exception('Unable to initiate shutdown!');
                }
                $client->send('OK', ['command' => $command]);

                break;

            case 'DELAY' :
                $payload->when = time() + $payload['value'];
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
                    $payload['tag'] ?? null,
                    $payload['overwrite'] ?? false
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
                if (!($name = $payload['name'] ?? null)) {
                    throw new \Exception('Unable to spawn a service without a service name!');
                }
                if (!($id = $this->spawn($client, $name, $payload))) {
                    throw new \Exception('Unable to spawn dynamic service: '.$name);
                }
                $client->send('OK', ['command' => $command, 'name' => $name, 'task_id' => $id]);

                break;

            case 'KILL':
                if (!($name = $payload['name'] ?? null)) {
                    throw new \Exception('Can not kill dynamic service without a name!');
                }
                if (!$this->kill($client, $name)) {
                    throw new \Exception('Unable to kill dynamic service '.$name);
                }
                $client->send('OK', ['command' => $command, 'name' => $payload]);

                break;

            case 'SIGNAL':
                if (!($eventID = $payload['id'] ?? null)) {
                    return false;
                }
                // Otherwise, send this signal to any child services for the requested type
                if (!($service = $payload['service'] ?? null)) {
                    return false;
                }
                if (!$this->signal($client, $eventID, $service, $payload['data'] ?? null)) {
                    throw new \Exception('Unable to signal dynamic service');
                }
                $client->send('OK', ['command' => $command, 'name' => $payload]);

                break;

            default:
                throw new \Exception('Unhandled command: '.$command);
        }

        return true;
    }

    public function trigger(string $eventID, mixed $data, ?string $clientID = null, ?string $triggerID = null): bool
    {
        if (null === $data) {
            return false;
        }
        $this->log->write(W_NOTICE, "TRIGGER: {$eventID}");
        ++$this->stats['events'];
        $this->rrd->setValue('events', 1);
        if (null === $triggerID) {
            $triggerID = uniqid();
        } elseif (array_key_exists($eventID, $this->events) && array_key_exists($triggerID, $this->events[$eventID])) {
            return true;
        }
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
        $buf = fread($stream, 65535);
        if (false === $buf) {
            $this->disconnect($stream);

            return false;
        }
        $bytesReceived = strlen($buf);
        if ($client = $this->clientGet($stream)) {
            if ('client' === $client->type) {
                if (0 === $bytesReceived) {
                    $this->log->write("Remote host {$client->address}:{$client->port} closed connection", LogLevel::NOTICE);
                    $this->disconnect($stream);

                    return false;
                }
                $this->log->write("CLIENT<-RECV: HOST={$client->address} PORT={$client->port} BYTES=".$bytesReceived, LogLevel::DEBUG);
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

    /**
     * Checks the status of connected clients and pings them if they have not sent any data
     * within the configured number of seconds.
     *
     * This function will only perform the check if the 'client.check' configuration value is
     * greater than 0 and there is at least one client connected.
     *
     * The default timeout for client inactivity is 60 seconds.
     */
    private function clientCheck(): void
    {
        if (!($this->config['client']['check'] > 0 && count($this->clients) > 0)) {
            return;
        }
        // Only ping if we havn't received data from the client for the configured number of seconds (default to 60).
        $when = time() - $this->config['client']['check'];
        foreach ($this->clients as $client) {
            if ($client->lastContact <= $when) {
                $client->ping();
            }
        }
    }

    private function eventCleanup(): void
    {
        if (false === $this->config['cleanup']
        || 0 === count($this->events)) {
            return;
        }
        foreach ($this->events as $eventID => $events) {
            foreach ($events as $id => $data) {
                if ((int) ($data['when'] + $this->config['event']['queueTimeout']) <= time()) {
                    if ($eventID != $this->config['admin']['trigger']) {
                        $this->log->write("EXPIRE: NAME={$eventID} TRIGGER={$id}", LogLevel::DEBUG);
                    }
                    unset($this->events[$eventID][$id]);
                }
            }
            if (0 === count($this->events[$eventID])) {
                unset($this->events[$eventID]);
            }
        }
    }

    private function socketSelect(): void
    {
        $read = $this->streams;
        $write = $except = null;
        if (@stream_select($read, $write, $except, $this->tv) > 0) {
            $this->tv = 0;
            foreach ($read as $stream) {
                if ($stream === $this->master) {
                    $clientSocket = stream_socket_accept($stream);
                    if ($clientSocket < 0) {
                        $this->log->write('Failed: socket_accept()', LogLevel::ERROR);

                        continue;
                    }
                    $socketId = (int) $clientSocket;
                    $this->streams[$socketId] = $clientSocket;
                    $peer = stream_socket_get_name($clientSocket, true);
                    $this->log->write("Connection from {$peer} with socket id #{$socketId}", LogLevel::NOTICE);
                } else {
                    $this->clientProcess($stream);
                }
            }
        } else {
            $this->tv = 1;
        }
    }

    private function writeStartupMessage(string $message = 'Warlock starting up...'): void
    {
        if ($this->silent) {
            return;
        }
        $this->log->write($message, LogLevel::INFO);
        $this->log->write('PHP Version = '.PHP_VERSION, LogLevel::NOTICE);
        $this->log->write('PHP Binary = '.$this->config['phpBinary'], LogLevel::NOTICE);
        // $this->log->write(W_NOTICE, 'Application path = '.APPLICATION_PATH);
        // $this->log->write(W_NOTICE, 'Application name = '.$this->config['sys']['applicationName']);
        $this->log->write('Application environment = '.APPLICATION_ENV, LogLevel::NOTICE);
        // $this->log->write(W_NOTICE, 'Runtime path = '.$runtimePath);
        // $this->log->write(W_NOTICE, 'PID = '.$this->pid);
        // $this->log->write(W_NOTICE, 'PID file = '.$this->pidfile);
        $this->log->write('Server ID = '.$this->config['serverId'], LogLevel::NOTICE);
        $this->log->write('Listen address = '.$this->config['listenAddress'], LogLevel::NOTICE);
        $this->log->write('Listen port = '.$this->config['port'], LogLevel::NOTICE);
        // $this->log->write(W_NOTICE, 'Task expiry = '.$this->config['task']['expire'].' seconds');
        // $this->log->write(W_NOTICE, 'Process timeout = '.$this->config['process']['timeout'].' seconds');
        // $this->log->write(W_NOTICE, 'Process limit = '.$this->config['process']['limit'].' processes');
    }
}
