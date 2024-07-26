<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Application;
use Hazaar\Application\Request\HTTP;
use Hazaar\Cron;
use Hazaar\Date;
use Hazaar\Map;
use Hazaar\Warlock\Connection\Pipe;
use Hazaar\Warlock\Connection\Socket;
use Hazaar\Warlock\Interfaces\Connection;

require 'Functions.php';
define('W_LOCAL', -1);

/**
 * @brief       The Warlock application service class
 *
 * @detail      Services are long running processes that allow code to be executed on the server in the background
 *              without affecting or requiring any interaction with the front-end. Services are managed by the Warlock
 *              process and can be set to start when Warlock starts or enabled/disabled manually using the
 *              Hazaar\Warlock\Control class.
 *
 *              Services are executed within the Application context and therefore have access to everything (configs,
 *              classes/models, cache, etc) that your application front-end does.
 *
 *              See the "Services Documentation":https://scroly.io/hazaarmvc/advanced-features/warlock/services for
 *              information on how to write and manage services.
 *
 * @module      warlock
 */
abstract class Service extends Process
{
    protected string $name;
    protected Map $config;

    /**
     * @var array<int, mixed>
     */
    protected array $schedule = [];              // callback execution schedule
    protected ?int $next = null;                 // Timestamp of next executable schedule item
    protected bool $slept = false;
    private int $lastHeartbeat;
    private int $lastCheckfile;
    private string $serviceFile;                    // The file in which the service is defined
    private int $serviceFileMtime;              // The last modified time of the service file

    /**
     * @var array<int>
     */
    private array $__logLevels = [];
    private int $__strPad = 0;
    private string $__logFile;

    /**
     * @var false|resource
     */
    private mixed $__log = null;

    private int $__localLogLevel = W_INFO;
    private bool $__remote = false;

    final public function __construct(Application $application, Protocol $protocol, bool $remote = false)
    {
        $class = get_class($this);
        if ('Service' === substr($class, -7)) {
            $this->name = strtolower(substr($class, 0, strlen($class) - 7));
        } else {
            $parts = explode('\\', $class);
            $this->name = strtolower(array_pop($parts));
        }
        $warlock = new Config();
        $this->log(W_LOCAL, 'Loaded config for '.APPLICATION_ENV);
        $defaults = [
            $this->name => [
                'enabled' => false,
                'heartbeat' => 60,
                'checkfile' => 1,
                'connect_retries' => 3,        // When establishing a control channel, make no more than this number of attempts before giving up
                'connect_retry_delay' => 100,  // When making multiple attempts to establish the control channel, wait this long between each
                'server' => [
                    'host' => '127.0.0.1',
                    'port' => $warlock['server']['port'],
                    'access_key' => $warlock->get('admin.key'),
                ],
                'silent' => false,
                'applicationName' => $warlock['sys']['applicationName'],
                'log' => $warlock['log'],
            ],
        ];
        $config = new Application\Config('service', APPLICATION_ENV, $defaults);
        if (!($this->config = ake($config, $this->name))) {
            throw new \Exception("Service '{$this->name}' is not configured!");
        }
        $consts = get_defined_constants(true);
        // Load the warlock log levels into an array.
        foreach ($consts['user'] as $name => $value) {
            if ('W_' == substr($name, 0, 2)) {
                $len = strlen($this->__logLevels[$value] = substr($name, 2));
                if ($len > $this->__strPad) {
                    $this->__strPad = $len;
                }
            }
        }
        $this->__remote = $remote;
        if ($this->config->log->has('level') && defined($out_level = $this->config->log->get('level'))) {
            $this->__localLogLevel = constant($out_level);
        }
        if (true === $remote && !$this->config->has('server')) {
            throw new \Exception("Warlock server required to run in remote service mode.\n");
        }
        $this->__logFile = $warlock['sys']['runtimePath'].DIRECTORY_SEPARATOR.$this->name.'.log';
        if ((file_exists($this->__logFile) && is_writable($this->__logFile)) || is_writable(dirname($this->__logFile))) {
            $this->__log = fopen($this->__logFile, 'a');
        }
        $this->log(W_LOCAL, "Service '{$this->name}' starting up");
        if (!$application->request instanceof HTTP) {
            $this->setErrorHandler('__errorHandler');
            $this->setExceptionHandler('__exceptionHandler');
        }
        if ($tz = $this->config->get('timezone')) {
            date_default_timezone_set($tz);
        }
        if ($this->config['checkfile'] > 0) {
            $reflection = new \ReflectionClass($this);
            $this->serviceFile = $reflection->getFileName();
            $this->serviceFileMtime = filemtime($this->serviceFile);
            $this->lastCheckfile = time();
        }
        parent::__construct($application, $protocol);
        if (method_exists($this, 'construct')) {
            $this->construct($this->application);
        }
    }

    public function __destruct()
    {
        if ($this->__log) {
            fclose($this->__log);
        }
        parent::__destruct();
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
        ob_start();
        $msg = "#{$errno} on line {$errline} in file {$errfile}\n"
            .str_repeat('-', 40)."\n{$errstr}\n".str_repeat('-', 40)."\n";
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $msg .= ob_get_clean()."\n";
        $this->log(W_LOCAL, 'ERROR '.$msg);
        $this->send('ERROR', $msg);

        return true;
    }

    final public function __exceptionHandler(\Throwable $e): bool
    {
        ob_start();
        $msg = "#{$e->getCode()} on line {$e->getLine()} in file {$e->getFile()}\n"
            .str_repeat('-', 40)."\n{$e->getMessage()}\n".str_repeat('-', 40)."\n";
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $msg .= ob_get_clean()."\n";
        $this->log(W_LOCAL, 'EXCEPTION '.$msg);
        $this->send('ERROR', $msg);

        return true;
    }

    final protected function __processCommand(string $command, mixed $payload = null): bool
    {
        switch ($command) {
            case 'STATUS':
                $this->__sendHeartbeat();

                break;

            case 'CANCEL':
                $this->stop();

                return true;
        }

        try {
            return parent::__processCommand($command, $payload);
        } catch (\Exception $e) {
            $this->__exceptionHandler($e);
        }

        return false;
    }

    private function __processSchedule(): void
    {
        if (!is_array($this->schedule) || !count($this->schedule) > 0) {
            return;
        }
        if (($count = count($this->schedule)) > 0) {
            $this->log(W_DEBUG, "Processing {$count} scheduled actions");
        }
        $this->next = null;
        foreach ($this->schedule as $id => &$exec) {
            if (time() >= $exec['when']) {
                $this->state = HAZAAR_SERVICE_RUNNING;
                if (is_string($exec['callback'])) {
                    $exec['callback'] = [$this, $exec['callback']];
                }

                try {
                    if (is_callable($exec['callback'])) {
                        $this->log(W_DEBUG, "RUN: ACTION={$exec['label']}");
                        call_user_func_array($exec['callback'], ake($exec, 'args', [], true));
                    } else {
                        $this->log(W_ERR, "Scheduled action {$exec['label']} is not callable!");
                    }
                } catch (\Exception $e) {
                    $this->__exceptionHandler($e);
                }

                switch ($exec['type']) {
                    case HAZAAR_SCHEDULE_INTERVAL:
                        if ($exec['when'] = time() + $exec['interval']) {
                            $this->log(W_DEBUG, "INTERVAL: ACTION={$exec['label']} NEXT=".date('Y-m-d H:i:s', $exec['when']));
                        }

                        break;

                    case HAZAAR_SCHEDULE_CRON:
                        if ($exec['when'] = $exec['cron']->getNextOccurrence()) {
                            $this->log(W_DEBUG, "SCHEDULED: ACTION={$exec['label']} NEXT=".date('Y-m-d H:i:s', $exec['when']));
                        }

                        break;

                    case HAZAAR_SCHEDULE_DELAY:
                    case HAZAAR_SCHEDULE_NORM:
                    default:
                        unset($this->schedule[$id]);

                        break;
                }
                if (null === $exec['when']
                    || 0 === $exec['when']
                    || (HAZAAR_SCHEDULE_INTERVAL !== $exec['type'] && $exec['when'] < time())) {
                    unset($this->schedule[$id]);
                    $this->log(W_DEBUG, "UNSCHEDULED: ACTION={$exec['label']}");
                }
            }
            if (null === $this->next || ($exec['when'] && $exec['when'] < $this->next)) {
                $this->next = $exec['when'];
            }
        }
        if (null !== $this->next) {
            $this->log(W_NOTICE, 'Next scheduled action is at '.date('Y-m-d H:i:s', $this->next));
        }
    }

    final protected function __sendHeartbeat(): void
    {
        $status = [
            'pid' => getmypid(),
            'name' => $this->name,
            'start' => $this->start,
            'state_code' => $this->state,
            'state' => $this->__stateString($this->state),
            'mem' => memory_get_usage(),
            'peak' => memory_get_peak_usage(),
        ];
        $this->lastHeartbeat = time();
        $this->send('status', $status);
    }

    private function __stateString(?int $state = null): string
    {
        if (null === $state) {
            $state = $this->state;
        }

        return match ($state) {
            HAZAAR_SERVICE_NONE => 'Not Ready',
            HAZAAR_SERVICE_ERROR => 'Error',
            HAZAAR_SERVICE_INIT => 'Initializing',
            HAZAAR_SERVICE_READY => 'Ready',
            HAZAAR_SERVICE_RUNNING => 'Running',
            HAZAAR_SERVICE_SLEEP => 'Sleeping',
            HAZAAR_SERVICE_STOPPING => 'Stopping',
            HAZAAR_SERVICE_STOPPED => 'Stopped',
            default => 'Unknown',
        };
    }

    // @phpstan-ignore-next-line
    private function __rotateLogFiles(int $logfiles = 0): void
    {
        $this->log(W_LOCAL, 'ROTATING LOG FILES: MAX='.$logfiles);
        fclose($this->__log);
        rotateLogFile($this->__logFile, $logfiles);
        $this->__log = fopen($this->__logFile, 'a');
        $this->log(W_LOCAL, 'New log file started');
    }

    public function log(int $level, mixed $message, ?string $name = null): bool
    {
        if (null === $name) {
            $name = $this->name;
        }
        if (W_LOCAL !== $level) {
            parent::log($level, $message, $name);
        }
        if (is_resource($this->__log)) {
            if ($level <= $this->__localLogLevel) {
                $label = ake($this->__logLevels, $level, 'NONE');
                if (!is_array($message)) {
                    $message = [$message];
                }
                foreach ($message as $m) {
                    $msg = date('Y-m-d H:i:s')." - {$this->name} - ".str_pad($label, $this->__strPad, ' ', STR_PAD_LEFT).' - '.$m."\n";
                    fwrite($this->__log, $msg);
                    if (true === $this->__remote && true !== $this->config['silent']) {
                        echo $msg;
                    }
                }
                fflush($this->__log);
            }
        }

        return true;
    }

    public function debug(mixed $data, ?string $name = null): bool
    {
        if (null === $name) {
            $name = $this->name;
        }

        return parent::debug($data, $name);
    }

    /**
     * @param array<mixed> $params
     */
    final public function main(?array $params = null, bool $dynamic = false): int
    {
        $this->log(W_LOCAL, 'Service started');
        $this->state = HAZAAR_SERVICE_INIT;
        if (true === $this->config['log']['rotate']) {
            $when = ake($this->config['log'], 'rotateAt', '0 0 * * * *');
            $logfiles = ake($this->config['log'], 'logfiles');
            $this->log(W_LOCAL, "Log rotation is enabled. WHEN={$when} LOGFILES={$logfiles}");
            $this->cron($when, '__rotateLogFiles', [$logfiles]);
        }
        $init = true;
        if (method_exists($this, 'init')) {
            $init = $this->init();
        }
        if (HAZAAR_SERVICE_INIT === $this->state) {
            $this->state = ((false === $init) ? HAZAAR_SERVICE_ERROR : HAZAAR_SERVICE_READY);
            if (HAZAAR_SERVICE_READY !== $this->state) {
                return 1;
            }
            $this->state = HAZAAR_SERVICE_RUNNING;
        }
        if (true === $dynamic) {
            if (!method_exists($this, 'runOnce')) {
                return 5;
            }
            if (false === $this->invokeMethod('runOnce', $params)) {
                return 1;
            }

            return 0;
        }
        if (!$this->start()) {
            return 1;
        }
        $this->__sendHeartbeat();
        $this->__processSchedule();
        $code = 0;
        while (HAZAAR_SERVICE_RUNNING == $this->state || HAZAAR_SERVICE_SLEEP == $this->state) {
            $this->slept = false;
            $this->state = HAZAAR_SERVICE_RUNNING;

            try {
                $ret = $this->invokeMethod('run', $params);
                if (false === $ret) {
                    $this->state = HAZAAR_SERVICE_STOPPING;
                }
                /*
                * If sleep was not executed in the last call to run(), then execute it now.  This protects bad services
                * from not sleeping as the sleep() call is where new signals are processed.
                */
                if (false === $this->slept) {
                    $this->sleep(0);
                }
                if ($this->serviceFileMtime > 0 && time() >= ($this->lastCheckfile + $this->config['checkfile'])) {
                    $this->lastCheckfile = time();
                    clearstatcache(true, $this->serviceFile);
                    // Check if the service file has been modified and initiate a restart
                    if (filemtime($this->serviceFile) > $this->serviceFileMtime) {
                        $this->log(W_INFO, 'Service file modified. Initiating restart.');
                        $this->state = HAZAAR_SERVICE_STOPPING;
                        $code = 6;
                    }
                }
            } catch (\Throwable $e) {
                $this->__exceptionHandler($e);
                $this->state = HAZAAR_SERVICE_ERROR;
                $code = 7;
            }
        }
        $this->state = HAZAAR_SERVICE_STOPPING;
        $this->log(W_INFO, 'Service is shutting down');
        $this->shutdown();
        $this->state = HAZAAR_SERVICE_STOPPED;

        return $code;
    }

    // BUILT-IN PLACEHOLDER METHODS
    public function run(): void
    {
        $this->sleep(60);
    }

    public function shutdown(): bool
    {
        return true;
    }

    final public function stop(): void
    {
        $this->state = HAZAAR_SERVICE_STOPPING;
    }

    final public function restart(): bool
    {
        $this->stop();

        return $this->start();
    }

    final public function state(): int
    {
        return $this->state;
    }

    final public function delay(
        int $seconds,
        callable|string $callback,
        ?Map $arguments = null
    ): bool|string {
        if (!is_int($seconds)) {
            return false;
        }
        if (!is_callable($callback) && !method_exists($this, $callback)) {
            return false;
        }
        $id = uniqid();
        $label = (is_string($callback) ? $callback : '<func>');
        $when = time() + $seconds;
        $this->schedule[$id] = [
            'type' => HAZAAR_SCHEDULE_DELAY,
            'label' => $label,
            'when' => $when,
            'callback' => $callback,
            'args' => $arguments->toArray(),
        ];
        if (null === $this->next || $when < $this->next) {
            $this->next = $when;
        }
        $this->log(W_DEBUG, "SCHEDULED: ACTION={$label} DELAY={$seconds} NEXT=".date('Y-m-d H:i:s', $when));

        return $id;
    }

    final public function interval(
        int $seconds,
        callable|string $callback,
        ?Map $params = null,
        ?string $tag = null,
        bool $overwrite = false
    ): false|string {
        if (!is_callable($callback) && !method_exists($this, $callback)) {
            return false;
        }
        $id = uniqid();
        $label = (is_string($callback) ? $callback : '<func>');
        // First execution in $seconds
        $when = time() + $seconds;
        $data = [
            'type' => HAZAAR_SCHEDULE_INTERVAL,
            'label' => $label,
            'when' => $when,
            'interval' => $seconds,
            'callback' => $callback,
            'args' => $params,
        ];
        if ($tag) {
            $data['tag'] = $tag;
            $data['overwrite'] = strbool($overwrite);
        }
        $this->schedule[$id] = $data;
        if (null === $this->next || $when < $this->next) {
            $this->next = $when;
        }
        $this->log(W_DEBUG, "SCHEDULED: ACTION={$label} INTERVAL={$seconds} NEXT=".date('Y-m-d H:i:s', $when));

        return $id;
    }

    final public function schedule(
        Date $date,
        callable|string $callback,
        ?Map $params = null,
        ?string $tag = null,
        bool $overwrite = false
    ): false|string {
        if (!is_callable($callback) && !method_exists($this, $callback)) {
            return false;
        }
        if ($date->getTimestamp() <= time()) {
            return false;
        }
        $id = uniqid();
        $label = (is_string($callback) ? $callback : '<func>');
        $when = $date->getTimestamp();
        $data = [
            'type' => HAZAAR_SCHEDULE_NORM,
            'label' => $label,
            'when' => $when,
            'callback' => $callback,
            'args' => $params,
        ];
        if ($tag) {
            $data['tag'] = $tag;
            $data['overwrite'] = strbool($overwrite);
        }
        $this->schedule[$id] = $data;
        if (null === $this->next || $when < $this->next) {
            $this->next = $when;
        }
        $this->log(W_DEBUG, "SCHEDULED: ACTION={$label} SCHEDULE={$date} NEXT=".date('Y-m-d H:i:s', $when));

        return $id;
    }

    /**
     * @param array<mixed> $arguments
     */
    final public function cron(
        string $format,
        callable|string $callback,
        ?array $arguments = null
    ): bool|string {
        if (!is_callable($callback) && !method_exists($this, $callback)) {
            return false;
        }
        $id = uniqid();
        $label = (is_string($callback) ? $callback : '<func>');
        $cron = new Cron($format);
        $when = $cron->getNextOccurrence();
        $this->schedule[$id] = [
            'type' => HAZAAR_SCHEDULE_CRON,
            'label' => $label,
            'when' => $when,
            'callback' => $callback,
            'args' => $arguments,
            'cron' => $cron,
        ];
        if (null === $this->next || $when < $this->next) {
            $this->next = $when;
        }
        $this->log(W_DEBUG, "SCHEDULED: ACTION={$label} CRON=\"{$format}\" NEXT=".date('Y-m-d H:i:s', $when));

        return $id;
    }

    final public function cancel(string $id): bool
    {
        if (!array_key_exists($id, $this->schedule)) {
            return false;
        }
        unset($this->schedule[$id]);

        return true;
    }

    final public function signal(string $eventID, mixed $data): bool
    {
        return $this->send('SIGNAL', ['service' => $this->name, 'id' => $eventID, 'data' => $data]);
    }

    final public function send(string $command, mixed $payload = null): bool
    {
        if (HAZAAR_SERVICE_NONE === $this->state) {
            return false;
        }
        $result = parent::send($command, $payload);
        if (false === $result) {
            $this->log(W_LOCAL, 'An error occured while sending command.  Stopping.');
            $this->stop();
        }

        return $result;
    }

    final public function recv(mixed &$payload = null, int $tv_sec = 3, int $tv_usec = 0): null|bool|string
    {
        if (HAZAAR_SERVICE_NONE === $this->state) {
            return false;
        }
        $result = parent::recv($payload, $tv_sec, $tv_usec);
        if (false === $result) {
            $this->log(W_LOCAL, 'An error occured while receiving data.  Stopping.');
            $this->stop();
        }

        return $result;
    }

    protected function connect(Protocol $protocol, ?string $guid = null): Connection|false
    {
        if (true === $this->__remote) {
            if (!$this->config->has('server')) {
                exit("Warlock server required to run in remote service mode.\n");
            }
            $headers = [];
            $headers['X-WARLOCK-ACCESS-KEY'] = base64_encode($this->config['server']['access_key']);
            $headers['X-WARLOCK-CLIENT-TYPE'] = 'service';
            $conn = new Socket($protocol);
            $this->log(W_LOCAL, 'Connecting to Warlock server at '.$this->config['server']['host'].':'.$this->config['server']['port']);
            if (!$conn->connect($this->config['applicationName'], $this->config['server']['host'], $this->config['server']['port'], $headers)) {
                return false;
            }
            if (($type = $conn->recv($payload)) === false || 'OK' !== $type) {
                return false;
            }
        } else {
            $conn = new Pipe($protocol, $guid);
        }

        return $conn;
    }

    /**
     * Sleep for a number of seconds.  If data is received during the sleep it is processed.  If the timeout is greater
     * than zero and data is received, the remaining timeout amount will be used in subsequent selects to ensure the
     * full sleep period is used.  If the timeout parameter is not set then the loop will just dump out after one
     * execution.
     */
    final protected function sleep(int $timeout = 0): bool
    {
        $start = microtime(true);
        $slept = false;
        // Sleep if we are still sleeping and the timeout is not reached.  If the timeout is NULL or 0 do this process at least once.
        while ($this->state < 4 && (false === $slept || ($start + $timeout) >= microtime(true))) {
            $tv_sec = 0;
            $tv_usec = 0;
            if ($timeout > 0) {
                $this->state = HAZAAR_SERVICE_SLEEP;
                $diff = ($start + $timeout) - microtime(true);
                $hb = $this->lastHeartbeat + $this->config['heartbeat'];
                $next = ((!$this->next || $hb < $this->next) ? $hb : $this->next);
                if (null != $next && $next < ($diff + time())) {
                    $diff = $next - time();
                }
                if ($diff > 0) {
                    $tv_sec = (int) floor($diff);
                    $tv_usec = (int) round(($diff - floor($diff)) * 1000000);
                } else {
                    $tv_sec = 1;
                }
            }
            $payload = null;
            if ($type = $this->recv($payload, $tv_sec, $tv_usec)) {
                $this->__processCommand($type, $payload);
            }
            if ($this->next > 0 && $this->next <= time()) {
                $this->__processSchedule();
            }
            if (($this->lastHeartbeat + $this->config['heartbeat']) <= time()) {
                $this->__sendHeartbeat();
            }
            $slept = true;
        }
        $this->slept = true;

        return true;
    }

    /**
     * @param array<mixed> $arguments
     */
    private function invokeMethod(string $method, ?array $arguments = null): mixed
    {
        $args = [];
        $initMethod = new \ReflectionMethod($this, $method);
        foreach ($initMethod->getParameters() as $parameter) {
            if (!($value = ake($arguments, $parameter->getName()))) {
                $value = $parameter->getDefaultValue();
            }
            $args[$parameter->getPosition()] = $value;
        }

        return $initMethod->invokeArgs($this, $args);
    }

    // CONTROL METHODS
    private function start(): bool
    {
        $events = $this->config->get('subscribe');
        if (null !== $events) {
            foreach ($events as $event_name => $event) {
                if (is_array($event)) {
                    if (!($action = ake($event, 'action'))) {
                        continue;
                    }
                    $this->subscribe($event_name, $action, ake($event, 'filter'));
                } else {
                    $this->subscribe($event_name, $event);
                }
            }
        }
        $schedule = $this->config->get('schedule');
        if (null !== $schedule) {
            foreach ($schedule as $item) {
                if (!($item instanceof Map && $item->has('action'))) {
                    continue;
                }
                if ($item->has('interval')) {
                    $this->interval(ake($item, 'interval'), ake($item, 'action'), ake($item, 'args'));
                }
                if ($item->has('delay')) {
                    $this->delay(ake($item, 'delay'), ake($item, 'action'), ake($item, 'args'));
                }
                if ($item->has('when')) {
                    $this->cron(ake($item, 'when'), ake($item, 'action'), ake($item, 'args'));
                }
            }
        }

        return true;
    }
}
