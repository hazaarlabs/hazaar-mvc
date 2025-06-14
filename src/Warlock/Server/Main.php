<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\Config;
use Hazaar\Util\Interval;
use Hazaar\Util\Str;
use Hazaar\Warlock\Enum\ClientType;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Enum\PcntlSignals;
use Hazaar\Warlock\Exception\ExtensionNotLoaded;
use Hazaar\Warlock\Logger;
use Hazaar\Warlock\Protocol;
use Hazaar\Warlock\Server\Client\Agent;
use Hazaar\Warlock\Server\Client\Peer;
use Hazaar\Warlock\Server\Component\Cluster;
use Hazaar\Warlock\Server\Component\KVStore;

if (!extension_loaded('sockets')) {
    throw new ExtensionNotLoaded('sockets');
}
if (!extension_loaded('pcntl')) {
    throw new ExtensionNotLoaded('pcntl');
}

class Main
{
    public Protocol $protocol;
    public Logger $log;

    /**
     * The server configuration.
     */
    public Config $config;

    /**
     * @var array<Main>
     */
    private static array $instances = [];
    private string $env = 'development';

    /**
     * The process ID of this server.
     */
    private int $pid = 0;
    private string $pidfile = '/var/run/warlock';

    private bool $silent = false;

    /**
     * Epoch of the last time stuff was processed.
     */
    private int $time = 0;
    private Watcher $watcher;

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
    private mixed $master;

    /**
     * Currently connected stream resources we are listening for data on.
     *
     * @var array<resource>
     */
    private array $streams = [];

    /**
     * Currently connected clients.
     *
     * @var array<Client>
     */
    private array $clients = [];

    /**
     * Currently connected agents.
     *
     * @var array<Client>
     */
    private array $agents = [];

    /**
     * Default select() timeout.
     */
    private int $tv = 1;

    private KVStore $kvStore;

    private Cluster $cluster;

    public function __construct(?string $configFile = null, string $env = 'development')
    {
        self::$instances[getmypid()] = $this;
        $this->config = new Config(sourceFile: $configFile, env: $this->env = $env, defaults: [
            'id' => 1,
            'listen' => '0.0.0.0',
            'port' => 13080,
            'encode' => false,
            'phpBinary' => PHP_BINARY,
            'runtime' => '/var/warlock',
            'pidfile' => '/var/run/warlock.pid',
            'log' => [
                'level' => 'debug',
            ],
            'timezone' => 'UTC',
            'client' => [
                'check' => 60,
            ],
            'event' => [
                'cleanup' => true,
                'timeout' => 5, // Message queue timeout.  Messages will hang around in the queue for this many seconds.  This allows late connections to
                // still get events and was the founding principle that allowed Warlock to work with long-polling HTTP connections.  Still
                // very useful in the WebSocket world though.
            ],
            'agent' => false, // Automatically start the agent process.  This is useful for testing and debugging, but not recommended for production use.
            'kvstore' => [
                'enabled' => false,
                'persist' => false, // Enable persistent storage for the KV Store.  This will store the KV Store in a file on disk.
                'compact' => 3600, // Compact the KV Store file on shutdown.
            ],
            'cluster' => [
                'enabled' => false,
                'hosts' => [], // The hosts to connect to for clustering.  This is a list of hostnames or IP addresses.
            ],
        ]);
        if (!isset($this->config['listen'])) {
            throw new \Exception('Server configuration not found');
        }
        if (isset($this->config['pidfile'])) {
            $this->pidfile = $this->config['pidfile'];
        }
        $logLevel = LogLevel::fromString($this->config['log']['level']);
        $this->log = new Logger(level: $logLevel);
        $this->log->setPrefix('main');
        $this->protocol = new Protocol((string) $this->config['id'], $this->config['encode']);
        if ($tz = $this->config['timezone']) {
            date_default_timezone_set(timezoneId: $tz);
        }
    }

    private static function __signalHandler(int $signo, mixed $siginfo): void
    {
        if (!($instance = self::$instances[getmypid()] ?? null)) {
            return; // No instance found for this PID
        }
        $sigName = PcntlSignals::tryFrom($signo)->name ?? 'UNKNOWN';
        $instance->log->write('SIGNAL: '.$sigName, LogLevel::DEBUG);

        switch ($signo) {
            case SIGINT:
            case SIGTERM:
            case SIGQUIT:
                $instance->shutdown();

                break;
        }
    }

    /**
     * Bootstrap the server.
     *
     * This method will set up the server, create the socket and prepare it for accepting connections.
     * It will also set up the signal handlers for the server.
     *
     * @throws \Exception if the socket could not be created or configured
     */
    public function bootstrap(): self
    {
        $this->writeStartupMessage('Warlock starting up...');
        foreach (PcntlSignals::cases() as $sig) {
            pcntl_signal($sig->value, [$this, '__signalHandler'], true);
        }
        $this->log->write('Creating TCP socket stream on: '.$this->config['listen'].':'.$this->config['port'], LogLevel::NOTICE);
        if (!($this->master = stream_socket_server('tcp://'.$this->config['listen'].':'.$this->config['port'], $errno, $errstr))) {
            throw new \Exception($errstr, $errno);
        }
        $this->log->write('Configuring TCP socket', LogLevel::NOTICE);
        if (!stream_set_blocking($this->master, false)) {
            throw new \Exception('Failed: stream_set_blocking(0)');
        }
        if (true === $this->config['kvstore']['enabled']) {
            $this->log->write('Initialising KV Store', LogLevel::NOTICE);
            $this->kvStore = new KVStore($this, config: $this->config['kvstore']);
            if ($this->config['kvstore']['persist']) {
                $result = $this->kvStore->enablePersistentStorage($this->config['kvstore']['compact'] ?? 3600);
                if (false === $result) {
                    $this->log->write('Failed to enable persistent storage for KV Store', LogLevel::ERROR);
                }
            }
            $this->log->write('KV Store component initialized', LogLevel::NOTICE);
        }
        if ($this->config['cluster']['enabled'] ?? false) {
            $this->log->write('Initialising Cluster Manager', LogLevel::NOTICE);
            $this->cluster = new Cluster($this->log, $this->config['cluster'] ?? []);
        }
        $this->streams[0] = $this->master;
        $this->running = true;
        $this->pid = getmypid();
        file_put_contents($this->pidfile, $this->pid);

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
        if (isset($this->cluster)) {
            $this->cluster->start($this);
        }
        $this->log->write('Ready...', LogLevel::INFO);
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

                if (isset($this->kvStore)) {
                    $this->kvStore->expireKeys();
                }
                if (isset($this->cluster)) {
                    $this->cluster->process();
                }
                $this->time = $now;
            }
        }
        if (isset($this->cluster)) {
            $this->cluster->stop();
        }
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

    public function stop(bool $force = false, ?int $pid = null): bool
    {
        if (null === $pid) {
            $pid = file_get_contents($this->pidfile);
            if (false === $pid) {
                return false;
            }
            $pid = (int) $pid;
            if (0 === $pid) {
                return false;
            }
        }

        return posix_kill($pid, $force ? SIGKILL : SIGTERM);
    }

    public function setSilent(bool $silent = true): void
    {
        $this->log->setSilent($silent);
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
    public function clientAddStream(mixed $stream, ClientType $type = ClientType::BASIC): Client|false
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
        $client = match ($type) {
            ClientType::BASIC => new Client($this, $stream),
            ClientType::AGENT => new Agent($this, $stream),
            ClientType::PEER => new Peer($this, $stream, $this->config['cluster'] ?? []),
            default => null,
        };
        if (!($client && $this->clientAdd($client))) {
            return false;
        }
        if ($client instanceof Agent) {
            $this->agents[$client->id] = $client; // Add new agent
        } elseif ($client instanceof Peer) {
            if (!isset($this->cluster)) {
                $this->log->write('Cluster component not initialized, cannot add peer client', LogLevel::ERROR);

                return false;
            }
            $this->cluster->addPeer($client);
        }

        return $client;
    }

    public function clientAdd(Client $client): bool
    {
        if (!$client->stream) {
            return false;
        }
        $streamID = (int) $client->stream;
        if (array_key_exists($streamID, $this->clients)) {
            return false; // Client already exists
        }
        $this->log->write("CLIENT->ADD: CLIENT={$client->id}", LogLevel::DEBUG);
        $this->clients[$streamID] = $client;
        $this->streams[$streamID] = $client->stream;

        return true;
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
        unset($this->agents[$client->id], $this->clients[$streamID], $this->streams[$streamID]);

        return true;
    }

    /**
     * Process administative commands for a client.
     */
    public function processCommand(Client $client, PacketType $command, mixed &$payload): void
    {
        if (true !== $client->authenticated()) {
            throw new \Exception('Admin commands only allowed by authorised clients!');
        }
        if (isset($this->kvStore) && 'KV' === substr($command->name, 0, 2)) {
            $this->kvStore->process($client, $command, $payload);

            return;
        }
        if (isset($this->cluster) && 'PEER' === substr($command->name, 0, 4)) {
            if (!$client instanceof Peer) {
                throw new \Exception('Cluster commands only allowed by peer clients!');
            }
            $this->cluster->processCommand($client, $command, $payload);

            return;
        }
        $this->log->write("ADMIN_COMMAND: {$command->name}".($client->id ? " CLIENT={$client->id}" : null), LogLevel::DEBUG);

        switch ($command) {
            case PacketType::DELAY:
                // Send a delay command to a random agent that is connected.
                if (0 === count($this->agents)) {
                    $client->send(PacketType::ERROR, ['message' => 'No agents connected']);

                    return;
                }
                $agentIndex = rand(0, count($this->agents) - 1);
                $agent = array_values($this->agents)[$agentIndex];
                $result = $agent->send(PacketType::DELAY, $payload);
                if (!$result) {
                    $client->send(PacketType::ERROR, ['message' => 'Failed to send delay command to agent']);

                    return;
                }
                $this->log->write("DELAY: AGENT={$agent->id} DELAY={$payload->value}", LogLevel::NOTICE);
                $client->send(PacketType::OK, ['task_id' => uniqid()]);

                break;

            case PacketType::SHUTDOWN:
                $delay = $payload->delay ?? 0;
                $this->log->write("Shutdown requested (Delay: {$delay})", LogLevel::NOTICE);
                if (!$this->shutdown($delay)) {
                    throw new \Exception('Unable to initiate shutdown!');
                }
                $client->send(PacketType::OK, ['command' => $command]);

                break;

            case PacketType::STATUS:
                $this->log->write("CLIENT<-STATUS: HOST={$client->address} PORT={$client->port} CLIENT={$client->id}", LogLevel::DEBUG);
                $this->log->write("STATUS: CLIENT={$client->id} UPTIME=".Interval::uptime(time() - $payload->start)
                    .' MEM='.Str::fromBytes($payload->mem)
                    .' PEAK='.Str::fromBytes($payload->peak), LogLevel::INFO);

                break;

            default:
                throw new \Exception('Unhandled command: '.$command->name);
        }
    }

    public function trigger(string $eventID, mixed $data, ?string $clientID = null, ?string $triggerID = null): bool
    {
        if (null === $data) {
            return false;
        }
        $this->log->write("TRIGGER: {$eventID}", LogLevel::NOTICE);
        if (null === $triggerID) {
            $triggerID = uniqid();
        } elseif (array_key_exists($eventID, $this->events)
            && array_key_exists($triggerID, $this->events[$eventID])) {
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
        $this->log->write("CLIENT<-QUEUE: EVENT={$eventID} CLIENT={$client->id}", LogLevel::DEBUG);
        $this->subscriptions[$eventID][$client->id] = [
            'client' => $client,
            'since' => time(),
            'filter' => $filter,
        ];
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
        $this->log->write("CLIENT<-DEQUEUE: NAME={$eventID} CLIENT={$client->id}", LogLevel::DEBUG);
        unset($this->subscriptions[$eventID][$client->id]);

        return true;
    }

    public function getStatus(): \stdClass
    {
        return (object) [
            'running' => $this->running,
            'pid' => $this->pid,
            'pidfile' => $this->pidfile,
            'clients' => count($this->clients),
            'streams' => count($this->streams),
            'subscriptions' => count($this->subscriptions),
            'events' => count($this->events),
        ];
    }

    public function getLogger(string $name): Logger
    {
        return $this->log->getNewChildLogger($name);
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
        $this->log->write('Checking event filter for \''.$event['id'].'\'', LogLevel::DEBUG);
        foreach ($filter as $field => $data) {
            $field = explode('.', $field);
            if (!$this->fieldExists($field, $event['data'])) {
                return false;
            }
            $fieldValue = $this->getFieldValue($field, $event['data']);
            if ($data instanceof \stdClass) { // If $data is an array it's a complex filter
                foreach (get_object_vars($data) as $filterType => $filterValue) {
                    switch ($filterType) {
                        case 'is':
                            if ($fieldValue != $filterValue) {
                                return true;
                            }

                            break;

                        case 'not':
                            if ($fieldValue === $filterValue) {
                                return true;
                            }

                            break;

                        case 'like':
                            if (!preg_match($filterValue, $fieldValue)) {
                                return true;
                            }

                            break;

                        case 'in':
                            if (!in_array($fieldValue, $filterValue)) {
                                return true;
                            }

                            break;

                        case 'nin':
                            if (in_array($fieldValue, $filterValue)) {
                                return true;
                            }

                            break;
                    }
                }
            } else { // Otherwise it's a simple filter with an acceptable value in it
                if ($fieldValue != $data) {
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
        $this->log->write("QUEUE: EVENT={$eventID} COUNT={$count}", LogLevel::DEBUG);
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
                $this->log->write("Sending event '{$event['id']}' to {$client->id}", LogLevel::NOTICE);
                if (!$client->sendEvent($event['id'], $triggerID, $event['data'])) {
                    return false;
                }
                $event['seen'][] = $client->id;
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
        $this->log->write("QUEUE: NAME={$eventID} COUNT={$count}", LogLevel::DEBUG);
        // Get a list of triggers to process
        $triggers = (empty($triggerID) ? array_keys($this->events[$eventID]) : [$triggerID]);
        foreach ($triggers as $trigger) {
            if (!isset($this->events[$eventID][$trigger])) {
                continue;
            }
            $event = &$this->events[$eventID][$trigger];
            foreach ($this->cluster->peers as $peer) {
                if (in_array($peer->name, $event['seen'])) {
                    continue;
                }
                $peer->sendEvent($eventID, $triggerID, $event['data']);
                $event['seen'][] = $peer->name;
            }
            if (!array_key_exists($eventID, $this->subscriptions)) {
                continue;
            }
            foreach ($this->subscriptions[$eventID] as $clientID => $item) {
                if (in_array($clientID, $event['seen'])
                    || (is_array($item['filter']) && $this->eventFilter($event, $item['filter']))) {
                    continue;
                }
                $this->log->write("Sending event '{$event['id']}' to {$clientID}", LogLevel::NOTICE);
                if (!$item['client']->sendEvent($eventID, $trigger, $event['data'])) {
                    return false;
                }
                $event['seen'][] = $clientID;
            }
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
            if (0 === $bytesReceived) {
                $this->log->write("Remote host {$client->address}:{$client->port} closed connection", LogLevel::NOTICE);
                $this->disconnect($stream);

                return false;
            }
            $this->log->write("CLIENT<-RECV: HOST={$client->address} PORT={$client->port} BYTES=".$bytesReceived, LogLevel::DEBUG);
            $client->recv($buf);
        } else {
            $clientType = Client::getTypeFromHandshake($buf);
            if (!($client = $this->clientAddStream($stream, $clientType))) {
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
        if (false === $this->config['event']['cleanup']
            || 0 === count($this->events)) {
            return;
        }
        foreach ($this->events as $eventID => $events) {
            foreach ($events as $id => $data) {
                if ((int) ($data['when'] + $this->config['event']['timeout']) <= time()) {
                    $this->log->write("EVENT->EXPIRE: EVENT={$eventID} ID={$id}", LogLevel::DEBUG);
                    unset($this->events[$eventID][$id]);
                }
            }
            if (0 === count($this->events[$eventID])) {
                $this->log->write("EVENT->CLEANUP: EVENT={$eventID}", LogLevel::DEBUG);
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
        $this->log->write('Application environment = '.$this->env, LogLevel::NOTICE);
        // $this->log->write(W_NOTICE, 'Runtime path = '.$runtimePath);
        // $this->log->write(W_NOTICE, 'PID = '.$this->pid);
        // $this->log->write(W_NOTICE, 'PID file = '.$this->pidfile);
        $this->log->write('Server ID = '.$this->config['id'], LogLevel::NOTICE);
        $this->log->write('Listen address = '.$this->config['listen'], LogLevel::NOTICE);
        $this->log->write('Listen port = '.$this->config['port'], LogLevel::NOTICE);
        // $this->log->write(W_NOTICE, 'Task expiry = '.$this->config['task']['expire'].' seconds');
        // $this->log->write(W_NOTICE, 'Process timeout = '.$this->config['process']['timeout'].' seconds');
        // $this->log->write(W_NOTICE, 'Process limit = '.$this->config['process']['limit'].' processes');
    }
}
