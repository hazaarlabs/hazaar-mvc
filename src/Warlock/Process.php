<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Application;
use Hazaar\Util\Boolean;
use Hazaar\Util\DateTime;
use Hazaar\Util\Str;
use Hazaar\Warlock\Agent\Struct\Endpoint;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Enum\PcntlSignals;
use Hazaar\Warlock\Enum\Status;
use Hazaar\Warlock\Exception\ExtensionNotLoaded;
use Hazaar\Warlock\Interface\Connection;

if (!extension_loaded('sockets')) {
    throw new ExtensionNotLoaded('sockets');
}
if (!extension_loaded('pcntl')) {
    throw new ExtensionNotLoaded('pcntl');
}

abstract class Process
{
    /**
     * @var array<self>
     */
    public static array $instances = [];
    public int $start = 0;
    public Status $state = Status::STARTING;
    public Protocol $protocol;
    protected Connection $conn;
    protected bool $reconnect = false;
    protected ?string $id = null;

    /**
     * @var array<string,callable>
     */
    protected array $subscriptions = [];
    protected bool $slept = false;
    protected bool $silent = false;
    protected Logger $log;
    private int $lastHeartbeat = 0;

    public function __construct(Protocol $protocol)
    {
        self::$instances[getmypid()] = $this;
        $this->start = time();
        $this->protocol = $protocol;
        $this->id = Str::guid();
        $this->log = new Logger();
        set_error_handler([$this, '__errorHandler']);
        set_exception_handler([$this, '__exceptionHandler']);
        $conn = $this->createConnection($protocol, $this->id);
        if (false === $conn) {
            throw new \Exception('Failed to created connection!', 1);
        }
        $this->conn = $conn;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @param array<mixed> $errcontext
     */
    final public function __errorHandler(
        int $errno,
        string $errstr,
        ?string $errfile = null,
        ?int $errline = null,
        array $errcontext = []
    ): bool {
        if ($this->silent) {
            return false;
        }
        $this->log->write($errstr, LogLevel::ERROR);

        return true;
    }

    final public function __exceptionHandler(\Throwable $e): void
    {
        $this->log->write($e->getMessage(), LogLevel::ERROR);
    }

    public static function __signalHandler(int $signo, mixed $siginfo): void
    {
        $sigName = PcntlSignals::tryFrom($signo)->name ?? 'UNKNOWN';
        $pid = getmypid();
        if (!($pid && isset(self::$instances[$pid]))) {
            return;
        }
        $instance = self::$instances[$pid];
        $instance->log->write('SIGNAL: '.$sigName, LogLevel::DEBUG);

        switch ($signo) {
            case SIGINT:
            case SIGTERM:
            case SIGQUIT:
                $instance->cancel();

                break;
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function __kv_send_recv(PacketType $command, array $data): mixed
    {
        if (!$this->send($command, $data)) {
            return false;
        }
        $payload = false;
        if (($ret = $this->recv($payload)) !== $command) {
            $msg = "KVSTORE: Invalid response to command {$command->name} from server: ".var_export($ret, true);
            if (is_object($payload) && property_exists($payload, 'reason')) {
                $msg .= "\nError: {$payload->reason}";
            }

            throw new \Exception($msg);
        }

        return $payload;
    }

    final protected function __sendHeartbeat(): void
    {
        $status = [
            'pid' => getmypid(),
            'start' => $this->start,
            'state_code' => $this->state,
            'state' => $this->state->name,
            'mem' => memory_get_usage(),
            'peak' => memory_get_peak_usage(),
        ];
        $this->lastHeartbeat = time();
        $this->send(PacketType::STATUS, $status);
    }

    /**
     * Constructor placeholder for child classes.
     */
    public function construct(Application $application): void
    {
        // do nothing
    }

    /**
     * Initialisation placeholder for child classes.
     */
    public function init(): bool
    {
        return true;
    }

    public function send(PacketType $command, mixed $payload = null): bool
    {
        return $this->conn->send($command, $payload);
    }

    public function recv(mixed &$payload = null, int $tvSec = 3, int $tvUsec = 0): null|bool|PacketType
    {
        return $this->conn->recv($payload, $tvSec, $tvUsec);
    }

    public function ping(bool $waitPong = false): bool|string
    {
        $ret = $this->send(PacketType::PING, microtime(true));
        if (!$waitPong) {
            return $ret;
        }

        return $this->recv();
    }

    /**
     * @param array<string,mixed> $filter
     */
    public function subscribe(string $event, callable $callback, ?array $filter = null): bool
    {
        $this->subscriptions[$event] = $callback;
        $payload = [
            'id' => $event,
        ];
        if (null !== $filter) {
            $payload['filter'] = $filter;
        }

        return $this->send(PacketType::SUBSCRIBE, $payload);
    }

    public function unsubscribe(string $event): bool
    {
        if (!array_key_exists($event, $this->subscriptions)) {
            return false;
        }
        unset($this->subscriptions[$event]);

        return $this->send(PacketType::UNSUBSCRIBE, ['id' => $event]);
    }

    public function trigger(string $event, mixed $data = null, bool $echoSelf = false): bool
    {
        $packet = [
            'id' => $event,
            'echo' => $echoSelf,
        ];
        if (null !== $data) {
            $packet['data'] = $data;
        }

        return $this->send(PacketType::TRIGGER, $packet);
    }

    public function log(string $message, LogLevel $level = LogLevel::NOTICE, ?string $name = null): bool
    {
        return $this->send(PacketType::LOG, ['level' => $level->value, 'msg' => $message, 'name' => $name]);
    }

    public function debug(mixed $data, ?string $name = null): bool
    {
        return $this->send(PacketType::DEBUG, ['data' => $data, 'name' => $name]);
    }

    /**
     * @param array<mixed> $params
     */
    public function spawn(string $service, array $params = []): bool
    {
        return $this->send(PacketType::SPAWN, ['name' => $service, 'detach' => true, 'params' => $params]);
    }

    public function kill(string $service): bool
    {
        return $this->send(PacketType::KILL, ['name' => $service]);
    }

    public function status(): false|\stdClass
    {
        $this->send(PacketType::STATUS);
        if (PacketType::STATUS == $this->recv($packet)) {
            return $packet;
        }

        return false;
    }

    /**
     * @param array{string,string}|callable $callable this can be a string in the format 'Class::method' or a callable function
     * @param array<mixed>                  $params
     */
    public function exec(
        array|callable $callable,
        array $params = [],
        ?string $tag = null,
        bool $overwrite = false
    ): bool|string {
        return $this->sendExec('delay', ['value' => 0], $callable, $params, $tag, $overwrite);
    }

    /**
     * @param array{string,string}|callable $callable this can be a string in the format 'Class::method' or a callable function
     * @param array<mixed>                  $params
     */
    public function execDelay(
        int $delay,
        array|callable $callable,
        array $params = [],
        ?string $tag = null,
        bool $overwrite = false
    ): bool|string {
        return $this->sendExec('delay', ['value' => $delay], $callable, $params, $tag, $overwrite);
    }

    /**
     * @param array<mixed> $params
     */
    public function interval(
        int $seconds,
        string $callable,
        array $params = [],
        ?string $tag = null,
        bool $overwrite = false
    ): bool|string {
        return $this->sendExec('interval', ['value' => $seconds], $callable, $params, $tag, $overwrite);
    }

    /**
     * @param array<mixed> $params
     */
    public function schedule(
        DateTime $when,
        string $callable,
        array $params = [],
        ?string $tag = null,
        bool $overwrite = false
    ): bool|string {
        return $this->sendExec('schedule', ['when' => $when], $callable, $params, $tag, $overwrite);
    }

    public function cancelTask(string $taskID): bool
    {
        $this->send(PacketType::CANCEL, $taskID);

        return 'OK' == $this->recv();
    }

    public function startService(string $name): bool
    {
        $this->send(PacketType::ENABLE, $name);

        return 'OK' == $this->recv();
    }

    public function stopService(string $name): bool
    {
        $this->send(PacketType::DISABLE, $name);

        return 'OK' == $this->recv();
    }

    public function service(string $name): bool|string
    {
        $this->send(PacketType::SERVICE, $name);
        if (PacketType::SERVICE == $this->recv($payload)) {
            return $payload;
        }

        return false;
    }

    public function get(string $key, ?string $namespace = null): mixed
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVGET, $data);
    }

    public function set(
        string $key,
        mixed $value,
        ?int $timeout = null,
        ?string $namespace = null
    ): bool {
        $data = ['k' => $key, 'v' => $value];
        if ($namespace) {
            $data['n'] = $namespace;
        }
        if (null !== $timeout) {
            $data['t'] = $timeout;
        }

        return $this->__kv_send_recv(PacketType::KVSET, $data);
    }

    public function has(string $key, ?string $namespace = null): bool
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVHAS, $data);
    }

    public function del(string $key, ?string $namespace = null): bool
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVDEL, $data);
    }

    public function clear(?string $namespace = null): bool
    {
        $data = ($namespace ? ['n' => $namespace] : null);

        return $this->__kv_send_recv(PacketType::KVCLEAR, $data);
    }

    /**
     * List all keys in the key-value store.
     * If a namespace is provided, it will list keys only in that namespace.
     *
     * @return array<string>
     */
    public function list(?string $namespace = null): array
    {
        $data = ($namespace ? ['n' => $namespace] : null);

        return $this->__kv_send_recv(PacketType::KVLIST, $data);
    }

    public function pull(string $key, ?string $namespace = null): mixed
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVPULL, $data);
    }

    public function push(string $key, mixed $value, ?string $namespace = null): bool
    {
        $data = ['k' => $key, 'v' => $value];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVPUSH, $data);
    }

    public function pop(string $key, ?string $namespace = null): mixed
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVPOP, $data);
    }

    public function shift(string $key, ?string $namespace = null): mixed
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVSHIFT, $data);
    }

    public function unshift(string $key, mixed $value, ?string $namespace = null): bool
    {
        $data = ['k' => $key, 'v' => $value];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVUNSHIFT, $data);
    }

    public function incr(string $key, ?int $step = null, ?string $namespace = null): int
    {
        $data = ['k' => $key];
        if ($step > 0) {
            $data['s'] = $step;
        }
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVINCR, $data);
    }

    public function decr(string $key, ?int $step = null, ?string $namespace = null): int
    {
        $data = ['k' => $key];
        if ($step > 0) {
            $data['s'] = $step;
        }
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVDECR, $data);
    }

    /**
     * Get all keys in the key-value store.
     * If a namespace is provided, it will return keys only in that namespace.
     *
     * @return array<string>
     */
    public function keys(?string $namespace = null): array
    {
        $data = [];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVKEYS, $data);
    }

    /**
     * Get all values in the key-value store.
     * If a namespace is provided, it will return values only in that namespace.
     *
     * @return array<mixed>
     */
    public function vals(?string $namespace = null): array
    {
        $data = [];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVVALS, $data);
    }

    /**
     * Count the number of keys in the key-value store.
     * If a namespace is provided, it will count keys only in that namespace.
     */
    public function count(string $key, ?string $namespace = null): int
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv(PacketType::KVCOUNT, $data);
    }

    public function connect(): bool
    {
        if (!$this->conn->connect()) {
            $this->conn->disconnect();

            return false;
        }

        return true;
    }

    public function disconnect(): bool
    {
        return $this->conn->disconnect();
    }

    public function connected(): bool
    {
        return $this->conn->connected();
    }

    public function authenticate(string $username, string $password): bool
    {
        $this->send(PacketType::AUTH, ['identity' => base64_encode($username.':'.$password)]);
        $result = $this->recv($payload);
        if (PacketType::AUTH === $result && isset($payload->result) && true === $payload->result) {
            $this->log->write('Authentication successful for user: '.$payload->user, LogLevel::NOTICE);

            return true;
        }
        if (PacketType::ERROR === $result && isset($payload->reason)) {
            $this->log->write('Authentication failed: '.$payload->reason, LogLevel::ERROR);
        } else {
            $this->log->write('Authentication failed: Invalid response from server', LogLevel::ERROR);
        }

        return false;
    }

    /**
     * @param array<mixed> $params
     */
    final public function main(?array $params = null, bool $dynamic = false): int
    {
        $code = 0;
        $this->state = Status::RECONNECT;
        while (Status::STOPPING !== $this->state) {
            pcntl_signal_dispatch();

            try {
                $this->slept = false;
                if (Status::RECONNECT === $this->state) {
                    $this->log->write('Waiting for server...', LogLevel::NOTICE);
                    $this->state = Status::CONNECT;
                    $this->silent = true;
                }
                if (Status::CONNECT === $this->state) {
                    if ($this->connected()) {
                        $this->state = Status::RUNNING;
                    } else {
                        if ($this->connect()) {
                            $this->state = Status::INIT;
                        } else {
                            $this->silent = true;
                            sleep(1);

                            continue;
                        }
                    }
                }
                $this->silent = false;
                if (Status::INIT === $this->state) {
                    $init = $this->init();
                    $this->state = ((false === $init) ? Status::ERROR : Status::READY);
                    if (Status::READY !== $this->state) {
                        return 1;
                    }
                }
                if (Status::READY === $this->state) {
                    $this->state = Status::RUNNING;
                }
                if (Status::RUNNING === $this->state) {
                    $this->run();
                    /*
                     * If sleep was not executed in the last call to run(), then execute it now.  This protects bad services
                     * from not sleeping as the sleep() call is where new signals are processed.
                     */
                    if (false === $this->slept) {
                        $this->sleep(0);
                    }
                }
            } catch (\Throwable $e) {
                $this->__exceptionHandler($e);
            }
        }
        $this->state = Status::STOPPING;
        $this->shutdown();
        $this->state = Status::STOPPED;

        return $code;
    }

    public function shutdown(): void {}

    public function cancel(): void
    {
        $this->state = Status::STOPPING;
        $this->log->write('Stopping process', LogLevel::NOTICE);
    }

    public function stop(bool $force = false, ?string $pid = null): bool
    {
        $this->log->write('Stopping agent...', LogLevel::NOTICE);
        if ($force) {
            $this->log->write('Force stopping agent', LogLevel::NOTICE);
        }
        // If we have a pid, we can try to stop the process gracefully.
        if ($pid) {
            $this->log->write("Stopping agent with PID {$pid}", LogLevel::NOTICE);
            if (posix_kill((int) $pid, SIGTERM)) {
                $this->log->write("Sent SIGTERM to PID {$pid}", LogLevel::NOTICE);
            } else {
                $this->log->write("Failed to send SIGTERM to PID {$pid}", LogLevel::ERROR);
            }
        }

        return true;
    }

    final public function state(): Status
    {
        return $this->state;
    }

    // BUILT-IN PLACEHOLDER METHODS
    protected function run(): void
    {
        $this->sleep(60);
    }

    /**
     * Sleep for a number of seconds.  If data is received during the sleep it is processed.  If the timeout is greater
     * than zero and data is received, the remaining timeout amount will be used in subsequent selects to ensure the
     * full sleep period is used.  If the timeout parameter is not set then the loop will just dump out after one
     * execution.
     */
    final protected function sleep(int $timeout = 0, Status $checkStatus = Status::RUNNING): bool
    {
        $start = microtime(true);
        // Sleep if we are still sleeping and the timeout is not reached.  If the timeout is NULL or 0 do this process at least once.
        while ($checkStatus === $this->state && ($start + $timeout) >= microtime(true)) {
            $tvSec = 0;
            $tvUsec = 0;
            if ($timeout > 0) {
                $this->state = Status::SLEEP;
                $diff = ($start + $timeout) - microtime(true);
                if ($diff > 0) {
                    $tvSec = (int) floor($diff);
                    $tvUsec = (int) round(($diff - floor($diff)) * 1000000);
                } else {
                    $tvSec = 1;
                }
            }
            $payload = null;
            if ($type = $this->recv($payload, $tvSec, $tvUsec)) {
                $this->processCommand($type, $payload);
            } elseif (false === $type) {
                $this->log->write('Connection closed by server', LogLevel::ERROR);
                $this->state = $this->reconnect ? Status::RECONNECT : Status::STOPPING;
                $this->disconnect();
                $this->slept = true;

                return false;
            }
            if (($this->lastHeartbeat + 60) <= time()) {
                $this->__sendHeartbeat();
            }
            if (Status::SLEEP === $this->state) {
                $this->state = $checkStatus;
            }
        }
        $this->slept = true;

        return true;
    }

    protected function createConnection(Protocol $protocol, ?string $guid = null): Connection|false
    {
        return false;
    }

    protected function setErrorHandler(string $methodName): ?callable
    {
        if (!method_exists($this, $methodName)) {
            throw new \Exception('Unable to set error handler.  Method does not exist!', E_ALL);
        }

        return set_error_handler([$this, $methodName]);
    }

    protected function setExceptionHandler(string $methodName): ?callable
    {
        if (!method_exists($this, $methodName)) {
            throw new \Exception('Unable to set exception handler.  Method does not exist!');
        }

        return set_exception_handler([$this, $methodName]);
    }

    protected function processCommand(PacketType $command, ?\stdClass $payload = null): bool
    {
        switch ($command) {
            case PacketType::EVENT:
                if (!($payload instanceof \stdClass
                    && property_exists($payload, 'id')
                    && array_key_exists($payload->id, $this->subscriptions))) {
                    return false;
                }
                $func = $this->subscriptions[$payload->id];
                if (is_string($func)) {
                    $func = [$this, $func];
                }
                $result = false;
                $process = true;
                // Check if the callback is an object method and if a beforeEvent method exists
                if (is_array($func) && is_object($obj = $func[0] ?? null)
                    && method_exists($obj, 'beforeEvent')) {
                    $process = $obj->beforeEvent($payload);
                }
                if (false !== $process) {
                    $result = call_user_func_array($func, [$payload->data ?? null, $payload]);
                }
                if (false !== $result
                    && is_array($func)
                    && is_object($obj = $func[0] ?? null)
                    && method_exists($obj, 'afterEvent')) {
                    $obj->afterEvent($payload);
                }

                break;

            case PacketType::PONG:
                if (is_int($payload->data)) {
                    $tripMs = (microtime(true) - $payload->data) * 1000;
                    $this->send(PacketType::DEBUG, 'PONG received in '.$tripMs.'ms');
                } else {
                    $this->send(PacketType::ERROR, 'PONG received with invalid payload!');
                }

                break;

            case PacketType::OK:
                break;

            case PacketType::STATUS:
                $this->__sendHeartbeat();

                break;

            case PacketType::CANCEL:
                $this->stop();

                break;

            case PacketType::ERROR:
                $this->log->write('Error received from server: '.$payload->reason, LogLevel::ERROR);

                break;

            default:
                $this->send(PacketType::DEBUG, ['type' => get_class($this), 'data' => 'Unhandled command: '.$command->name]);

                break;
        }

        return true;
    }

    /**
     * @param array{string,string}|callable $callable this can be a string in the format 'Class::method' or a callable function
     * @param array<mixed>                  $params
     */
    private function sendExec(
        string $command,
        mixed $data,
        array|callable $callable,
        ?array $params = null,
        ?string $tag = null,
        bool $overwrite = false
    ): false|string {
        $data['application'] = [
            'env' => APPLICATION_ENV,
        ];
        $data['endpoint'] = Endpoint::create($callable, $params);
        if ($tag) {
            $data['tag'] = $tag;
            $data['overwrite'] = Boolean::toString($overwrite);
        }
        $this->send(PacketType::get($command), $data);
        if (PacketType::OK === $this->recv($payload, 3)) {
            return $payload->task_id;
        }

        return false;
    }
}
