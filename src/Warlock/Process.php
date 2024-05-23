<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Application;
use Hazaar\Date;
use Hazaar\Map;
use Hazaar\Warlock\Interfaces\Connection;

abstract class Process
{
    public int $start = 0;
    public ?int $state = HAZAAR_SERVICE_NONE;
    protected Connection $conn;
    protected string $id;
    protected Application $application;
    protected Protocol $protocol;

    /**
     * @var array<string,callable>
     */
    protected array $subscriptions = [];

    /**
     * @var array<string, array<null|string>>
     */
    private static array $options = [
        'serviceName' => ['n', 'name', 'serviceName', "\tStart a service directly from the command line."],
        'daemon' => ['d', 'daemon', null, "\t\t\t\tStart in daemon mode and wait for a startup packet."],
        'help' => [null, 'help', null, "\t\t\t\tPrint this message and exit."],
    ];

    /**
     * @var array<int,string>
     */
    private static array $opt = [];

    public function __construct(Application $application, Protocol $protocol, ?string $guid = null)
    {
        $this->start = time();
        $this->application = $application;
        $this->protocol = $protocol;
        $this->id = (null === $guid ? guid() : $guid);
        $conn = $this->connect($protocol, $guid);
        if (false === $conn) {
            throw new \Exception('Process initialisation failed!', 1);
        }
        $this->conn = $conn;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    protected function __processCommand(string $command, mixed $payload = null): bool
    {
        switch ($command) {
            case 'EVENT':
                if (!($payload instanceof \stdClass
                    && property_exists($payload, 'id')
                    && array_key_exists($payload->id, $this->subscriptions))) {
                    return false;
                }
                $func = $this->subscriptions[$payload->id];
                if (is_string($func)) {
                    $func = [$this, $func];
                }
                if (is_callable($func)) {
                    $process = true;
                    if (is_object($obj = ake($func, 0))
                        && method_exists($obj, 'beforeEvent')) {
                        $process = $obj->beforeEvent($payload);
                    }
                    if (false !== $process) {
                        $result = call_user_func_array($func, [ake($payload, 'data'), $payload]);
                        if (false !== $result
                            && is_object($obj = ake($func, 0))
                            && method_exists($obj, 'afterEvent')) {
                            $obj->afterEvent($payload);
                        }
                    }
                }

                break;

            case 'PONG':
                if (is_int($payload)) {
                    $trip_ms = (microtime(true) - $payload) * 1000;
                    $this->send('DEBUG', 'PONG received in '.$trip_ms.'ms');
                } else {
                    $this->send('ERROR', 'PONG received with invalid payload!');
                }

                break;

            case 'OK':
                break;

            default:
                $this->send('DEBUG', 'Unhandled command: '.$command);

                break;
        }

        return true;
    }

    /**
     * @param array<mixed> $data
     */
    private function __kv_send_recv(string $command, array $data): false|string
    {
        if (!$this->send($command, $data)) {
            return false;
        }
        $payload = null;
        if (($ret = $this->recv($payload)) !== $command) {
            $msg = "KVSTORE: Invalid response to command {$command} from server: ".var_export($ret, true);
            if (is_object($payload) && property_exists($payload, 'reason')) {
                $msg .= "\nError: {$payload->reason}";
            }

            throw new \Exception($msg);
        }

        return $payload;
    }

    public function send(string $command, mixed $payload = null): bool
    {
        return $this->conn->send($command, $payload);
    }

    public function recv(mixed &$payload = null, int $tv_sec = 3, int $tv_usec = 0): null|bool|string
    {
        return $this->conn->recv($payload, $tv_sec, $tv_usec);
    }

    public function ping(bool $waitPong = false): bool|string
    {
        $ret = $this->send('PING', microtime(true));
        if (!$waitPong) {
            return $ret;
        }

        return $this->recv();
    }

    /**
     * @param array<string,mixed> $filter
     */
    public function subscribe(string $event, string $callback, ?array $filter = null): bool
    {
        if (!method_exists($this, $callback)) {
            return false;
        }
        $this->subscriptions[$event] = [$this, $callback];

        return $this->send('SUBSCRIBE', ['id' => $event, 'filter' => $filter]);
    }

    public function unsubscribe(string $event): bool
    {
        if (!array_key_exists($event, $this->subscriptions)) {
            return false;
        }
        unset($this->subscriptions[$event]);

        return $this->send('UNSUBSCRIBE', ['id' => $event]);
    }

    public function trigger(string $event, mixed $data = null, bool $echoSelf = false): bool
    {
        $packet = [
            'id' => $event,
            'echo' => $echoSelf,
        ];
        if ($data) {
            $packet['data'] = $data;
        }

        return $this->send('TRIGGER', $packet);
    }

    /**
     * @param array<string>|string $message
     */
    public function log(int $level, array|string $message, ?string $name = null): bool
    {
        return $this->send('LOG', ['level' => $level, 'msg' => $message, 'name' => $name]);
    }

    public function debug(mixed $data, ?string $name = null): bool
    {
        return $this->send('DEBUG', ['data' => $data, 'name' => $name]);
    }

    /**
     * @param array<mixed> $params
     */
    public function spawn(string $service, array $params = []): bool
    {
        return $this->send('SPAWN', ['name' => $service, 'detach' => true, 'params' => $params]);
    }

    public function kill(string $service): bool
    {
        return $this->send('KILL', ['name' => $service]);
    }

    public function status(): false|\stdClass
    {
        $this->send('status');
        if ('STATUS' == $this->recv($packet)) {
            return $packet;
        }

        return false;
    }

    public function runDelay(
        int $delay,
        callable $callable,
        ?Map $params = null,
        ?string $tag = null,
        bool $overwrite = false
    ): bool|string {
        return $this->sendExec('delay', ['value' => $delay], $callable, $params, $tag, $overwrite);
    }

    public function interval(
        int $seconds,
        string $callable,
        ?Map $params = null,
        ?string $tag = null,
        bool $overwrite = false
    ): bool|string {
        return $this->sendExec('interval', ['value' => $seconds], $callable, $params, $tag, $overwrite);
    }

    public function schedule(
        Date $when,
        string $callable,
        ?Map $params = null,
        ?string $tag = null,
        bool $overwrite = false
    ): bool|string {
        return $this->sendExec('schedule', ['when' => $when], $callable, $params, $tag, $overwrite);
    }

    public function cancel(string $taskID): bool
    {
        $this->send('cancel', $taskID);

        return 'OK' == $this->recv();
    }

    public function startService(string $name): bool
    {
        $this->send('enable', $name);

        return 'OK' == $this->recv();
    }

    public function stopService(string $name): bool
    {
        $this->send('disable', $name);

        return 'OK' == $this->recv();
    }

    public function service(string $name): bool|string
    {
        $this->send('service', $name);
        if ('SERVICE' == $this->recv($payload)) {
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

        return $this->__kv_send_recv('KVGET', $data);
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

        return $this->__kv_send_recv('KVSET', $data);
    }

    public function has(string $key, ?string $namespace = null): false|string
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVHAS', $data);
    }

    public function del(string $key, ?string $namespace = null): false|string
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVDEL', $data);
    }

    public function clear(?string $namespace = null): false|string
    {
        $data = ($namespace ? ['n' => $namespace] : null);

        return $this->__kv_send_recv('KVCLEAR', $data);
    }

    public function list(?string $namespace = null): false|string
    {
        $data = ($namespace ? ['n' => $namespace] : null);

        return $this->__kv_send_recv('KVLIST', $data);
    }

    public function pull(string $key, ?string $namespace = null): false|string
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVPULL', $data);
    }

    public function push(string $key, mixed $value, ?string $namespace = null): false|string
    {
        $data = ['k' => $key, 'v' => $value];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVPUSH', $data);
    }

    public function pop(string $key, ?string $namespace = null): false|string
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVPOP', $data);
    }

    public function shift(string $key, ?string $namespace = null): false|string
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVSHIFT', $data);
    }

    public function unshift(string $key, mixed $value, ?string $namespace = null): false|string
    {
        $data = ['k' => $key, 'v' => $value];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVUNSHIFT', $data);
    }

    public function incr(string $key, ?int $step = null, ?string $namespace = null): false|string
    {
        $data = ['k' => $key];
        if ($step > 0) {
            $data['s'] = $step;
        }
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVINCR', $data);
    }

    public function decr(string $key, ?int $step = null, ?string $namespace = null): false|string
    {
        $data = ['k' => $key];
        if ($step > 0) {
            $data['s'] = $step;
        }
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVDECR', $data);
    }

    public function keys(?string $namespace = null): false|string
    {
        $data = [];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVKEYS', $data);
    }

    public function vals(?string $namespace = null): false|string
    {
        $data = [];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVVALS', $data);
    }

    public function count(string $key, ?string $namespace = null): false|string
    {
        $data = ['k' => $key];
        if ($namespace) {
            $data['n'] = $namespace;
        }

        return $this->__kv_send_recv('KVCOUNT', $data);
    }

    /**
     * @brief Execute code from standard input in the application context
     *
     * @detail This method will accept Hazaar Protocol commands from STDIN and execute them.
     *
     * Exit codes:
     *
     * * 1 - Bad Payload - The execution payload could not be decoded.
     * * 2 - Unknown Payload Type - The payload execution type is unknown.
     * * 3 - Service Class Not Found - The service could not start because the service class could not be found.
     * * 4 - Unable to open control channel - The application was unable to open a control channel back to the execution server.
     *
     * @param array<mixed> $argv
     */
    public static function runner(Application $application, ?array $argv = null): int
    {
        posix_setsid();
        $options = self::getopt();
        $serviceName = ake($options, 'serviceName', null);
        if (!class_exists('\Hazaar\Warlock\Config')) {
            throw new \Exception('Could not find default warlock config.  How is this even working!!?');
        }
        $exitcode = 1;
        $_SERVER['WARLOCK_EXEC'] = 1;
        $warlock = new Config();
        define('RESPONSE_ENCODED', $warlock['server']['encoded']);
        $protocol = new Protocol($warlock['sys']['id'], $warlock['server']['encoded']);

        try {
            if (array_key_exists('daemon', $options)) {
                // Execution should wait here until we get a command
                $line = fgets(STDIN);
                $payload = null;
                if ($type = $protocol->decode($line, $payload)) {
                    if (!$payload instanceof \stdClass) {
                        throw new \Exception('Got Hazaar protocol packet without payload!');
                    }
                    // Synchronise the timezone with the server
                    if ($tz = ake($payload, 'timezone')) {
                        date_default_timezone_set($tz);
                    }
                    if ($config = ake($payload, 'config')) {
                        $application->config->extend($config);
                    }

                    switch ($type) {
                        case 'EXEC' :
                            $code = null;
                            $class = $method = null;
                            if (is_array($payload->exec)) {
                                $class = new \ReflectionClass($payload->exec[0]);
                                if (!$class->hasMethod($payload->exec[1])) {
                                    throw new \Exception('EXEC FAILED: Method '.$payload->exec[0].'::'.$payload->exec[1].' does not exist');
                                }
                                $method = $class->getMethod($payload->exec[1]);
                                if ($method->isStatic() && $method->isPublic()) {
                                    $file = file($method->getFileName());
                                    $start_line = $method->getStartLine() - 1;
                                    $end_line = $method->getEndLine();
                                    if (preg_match('/function\s+\w+(\(.*)/', $file[$start_line], $matches)) {
                                        $file[$start_line] = 'function'.$matches[1];
                                    }
                                    if ($namespace = $class->getNamespaceName()) {
                                        $code = "namespace {$namespace};\n\n";
                                    }
                                    $code .= '$_function = '.implode("\n", array_splice($file, $start_line, $end_line - $start_line)).';';
                                }
                            } else {
                                $code = '$_function = '.$payload->exec.';';
                            }
                            if (is_string($code)) {
                                $container = new Container($application, $protocol);
                                $exitcode = $container->exec($code, ake($payload, 'params'));
                            } elseif ($class instanceof \ReflectionClass
                                && $method instanceof \ReflectionMethod
                                && $class->isInstantiable()
                                && $class->isSubclassOf('Hazaar\\Warlock\\Process')
                                && $method->isPublic()
                                && !$method->isStatic()) {
                                $process = $class->newInstance($application, $protocol);
                                if ($class->isSubclassOf('Hazaar\\Warlock\\Service')) {
                                    $process->state = HAZAAR_SERVICE_RUNNING;
                                }
                                $method->invokeArgs($process, ake($payload, 'params', []));
                                $exitcode = 0;
                            } else {
                                throw new \Exception('Method can not be executed.');
                            }

                            break;

                        case 'SERVICE' :
                            if (!property_exists($payload, 'name')) {
                                $exitcode = 3;

                                break;
                            }
                            $service = self::getServiceClass($payload->name, $application, $protocol, false);
                            if ($service instanceof Service) {
                                $exitcode = call_user_func([$service, 'main'], ake($payload, 'params'), ake($payload, 'dynamic', false));
                            } else {
                                $exitcode = 3;
                            }

                            break;

                        default:
                            $exitcode = 2;

                            break;
                    }
                }
            } elseif (is_string($serviceName)) {
                $service = self::getServiceClass($serviceName, $application, $protocol, true);
                if (!$service instanceof Service) {
                    throw new \Exception("Could not find service named '{$serviceName}'.\n");
                }
                $exitcode = call_user_func([$service, 'main']);
            } else {
                $exitcode = self::showHelp();
            }
        } catch (\Exception $e) {
            echo $protocol->encode('ERROR', $e->getMessage())."\n";
            flush();
            if (($code = $e->getCode()) > 0) {
                $exitcode = $code;
            } else {
                $exitcode = 3;
            }
        }

        return $exitcode;
    }

    public static function getServiceClass(
        string $serviceName,
        Application $application,
        Protocol $protocol,
        bool $remote = false
    ): false|Service {
        $class_search = [
            'Application\\Services\\'.ucfirst($serviceName),
            ucfirst($serviceName).'Service',
        ];
        $service = null;
        foreach ($class_search as $serviceClass) {
            if (!class_exists($serviceClass)) {
                continue;
            }
            $service = new $serviceClass($application, $protocol, $remote);

            break;
        }

        return $service;
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

    protected function connect(Protocol $protocol, ?string $guid = null): Connection|false
    {
        return false;
    }

    protected function disconnect(): void
    {
        $this->conn->disconnect();
    }

    protected function connected(): bool
    {
        return $this->conn->connected();
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeCallable(callable|\Closure $callable): array
    {
        if ($callable instanceof \Closure) {
            $callable = (string) new \Hazaar\Closure($callable);
        } elseif (is_array($callable)) {
            if (is_object($callable[0])) {
                $reflectionMethod = new \ReflectionMethod($callable[0], $callable[1]);
                $classname = get_class($callable[0]);
                if (!$reflectionMethod->isStatic()) {
                    throw new \Exception('Method '.$callable[1].' of class '.$classname.' must be static');
                }
                $callable[0] = $classname;
            } elseif (2 !== count($callable)) {
                throw new \Exception('Invalid callable definition!');
            }
        } elseif (is_string($callable)) {
            if (false === strpos($callable, '::')) {
                $callable = [get_class($this), $callable];
            } else {
                $callable = explode('::', $callable);
            }
        }

        return ['callable' => $callable];
    }

    private function sendExec(
        string $command,
        mixed $data,
        callable $callable,
        ?Map $params = null,
        ?string $tag = null,
        bool $overwrite = false
    ): false|string {
        $data['application'] = [
            'env' => APPLICATION_ENV,
        ];
        $data['exec'] = $this->makeCallable($callable);
        if ($tag) {
            $data['tag'] = $tag;
            $data['overwrite'] = strbool($overwrite);
        }
        $data['exec']['params'] = $params;
        $this->send($command, $data);
        if ('OK' == $this->recv($payload)) {
            return $payload->task_id;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getopt(): array|int
    {
        if (!self::$opt) {
            self::$opt = [0 => '', 1 => []];
            foreach (self::$options as $name => $o) {
                if ($o[0]) {
                    self::$opt[0] .= $o[0].(null === $o[2] ? '' : ':');
                }
                if ($o[1]) {
                    self::$opt[1][] = $o[1].(null === $o[2] ? '' : ':');
                }
            }
        }
        $ops = getopt(self::$opt[0], self::$opt[1]);
        $options = [];
        foreach (self::$options as $name => $o) {
            $s = $l = false;
            $sk = $lk = null;
            if (($o[0] && ($s = array_key_exists($sk = rtrim($o[0], ':'), $ops))) || ($o[1] && ($l = array_key_exists($lk = rtrim($o[1], ':'), $ops)))) {
                $options[$name] = ($s ? $ops[$sk] : $ops[$lk]);
            }
        }
        if (true === ake($options, 'help')) {
            return self::showHelp();
        }

        return $options;
    }

    private static function showHelp(): int
    {
        $script = basename($_SERVER['SCRIPT_FILENAME']);
        $msg = "Syntax: {$script} [options]\nOptions:\n";
        foreach (self::$options as $o) {
            $avail = [];
            if ($o[0]) {
                $avail[] = '-'.$o[0].' '.$o[2];
            }
            if ($o[1]) {
                $avail[] = '--'.$o[1].'='.$o[2];
            }
            $msg .= '  '.implode(', ', $avail).$o[3]."\n";
        }
        echo $msg;

        return 0;
    }
}
