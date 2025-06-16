<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Application;
use Hazaar\Util\Boolean;
use Hazaar\Util\Cron;
use Hazaar\Util\DateTime;
use Hazaar\Warlock\Agent\Container;
use Hazaar\Warlock\Connection\Pipe;
use Hazaar\Warlock\Connection\Socket;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\ScheduleType;
use Hazaar\Warlock\Enum\Status;
use Hazaar\Warlock\Logger\WarlockWriter;

require 'Server/Functions.php';
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
abstract class Service extends Container
{
    protected string $name;

    /**
     * @var array<mixed>
     */
    protected array $config;

    /**
     * @var array<int, mixed>
     */
    protected array $schedule = [];              // callback execution schedule
    protected ?int $next = null;                 // Timestamp of next executable schedule item
    protected bool $slept = false;

    private int $lastCheckfile;
    private string $serviceFile;                    // The file in which the service is defined
    private int $serviceFileMtime;              // The last modified time of the service file

    /**
     * @param array<mixed> $config
     */
    final public function __construct(Protocol $protocol, ?string $name = null, array $config = [])
    {
        parent::__construct($protocol);
        $this->log->setLevel(LogLevel::DEBUG);
        $this->log->setPrefix('service');
        $this->log->setWriter(new WarlockWriter($this));
        $this->log->write('Initializing service', LogLevel::NOTICE);
        $this->name = $name ?? 'Unnamed Service';
        $this->config = $config;
        $this->setErrorHandler('__errorHandler');
        $this->setExceptionHandler('__exceptionHandler');
        if ($tz = $this->config['timezone']) {
            date_default_timezone_set($tz);
        }
        if (isset($this->config['checkfile'])) {
            $reflection = new \ReflectionClass($this);
            $this->serviceFile = $reflection->getFileName();
            $this->serviceFileMtime = filemtime($this->serviceFile);
            $this->lastCheckfile = time();
        }
        $this->initialize($this->config);
        $this->log->write('Service initialized: '.$this->name, LogLevel::NOTICE);
    }

    private function __processSchedule(): void
    {
        if (0 === count($this->schedule)) {
            return;
        }
        $count = count($this->schedule);
        $this->log("Processing {$count} scheduled actions", LogLevel::DEBUG);
        $this->next = null;
        foreach ($this->schedule as $id => &$exec) {
            if (time() >= $exec['when']) {
                $this->state = Status::RUNNING;
                if (is_string($exec['callback'])) {
                    $exec['callback'] = [$this, $exec['callback']];
                }

                try {
                    if (is_callable($exec['callback'])) {
                        $this->log("RUN: ACTION={$exec['label']}", LogLevel::DEBUG);
                        call_user_func_array($exec['callback'], $exec['args'] ?? []);
                    } else {
                        $this->log("Scheduled action {$exec['label']} is not callable!", LogLevel::ERROR);
                    }
                } catch (\Exception $e) {
                    $this->__exceptionHandler($e);
                }

                switch ($exec['type']) {
                    case ScheduleType::INTERVAL:
                        if ($exec['when'] = time() + $exec['interval']) {
                            $this->log("INTERVAL: ACTION={$exec['label']} NEXT=".date('Y-m-d H:i:s', $exec['when']), LogLevel::DEBUG);
                        }

                        break;

                    case ScheduleType::CRON:
                        if ($exec['when'] = $exec['cron']->getNextOccurrence()) {
                            $this->log("SCHEDULED: ACTION={$exec['label']} NEXT=".date('Y-m-d H:i:s', $exec['when']), LogLevel::DEBUG);
                        }

                        break;

                    case ScheduleType::DELAY:
                    case ScheduleType::NORM:
                    default:
                        unset($this->schedule[$id]);

                        break;
                }
                if (null === $exec['when']
                    || 0 === $exec['when']
                    || (ScheduleType::INTERVAL !== $exec['type'] && $exec['when'] < time())) {
                    unset($this->schedule[$id]);
                    $this->log("UNSCHEDULED: ACTION={$exec['label']}", LogLevel::DEBUG);
                }
            }
            if (null === $this->next || ($exec['when'] && $exec['when'] < $this->next)) {
                $this->next = $exec['when'];
            }
        }
        if (null !== $this->next) {
            $this->log('Next scheduled action is at '.date('Y-m-d H:i:s', $this->next), LogLevel::NOTICE);
        }
    }

    /**
     * Placeholder for the run method in case the service does not implement its own run method.
     */
    public function run(): void
    {
        $this->sleep(60);
    }

    /**
     * @param array<mixed> $arguments
     */
    final public function delay(
        int $seconds,
        callable|string $callback,
        array $arguments = []
    ): bool|string {
        if (!is_callable($callback) && !method_exists($this, $callback)) {
            return false;
        }
        $id = uniqid();
        $label = (is_string($callback) ? $callback : '<func>');
        $when = time() + $seconds;
        $this->schedule[$id] = [
            'type' => ScheduleType::DELAY,
            'label' => $label,
            'when' => $when,
            'callback' => $callback,
            'args' => $arguments,
        ];
        if (null === $this->next || $when < $this->next) {
            $this->next = $when;
        }
        $this->log("SCHEDULED: ACTION={$label} DELAY={$seconds} NEXT=".date('Y-m-d H:i:s', $when), LogLevel::DEBUG);

        return $id;
    }

    final public function interval(
        int $seconds,
        callable|string $callback,
        array $params = [],
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
            'type' => ScheduleType::INTERVAL,
            'label' => $label,
            'when' => $when,
            'interval' => $seconds,
            'callback' => $callback,
            'args' => $params,
        ];
        if ($tag) {
            $data['tag'] = $tag;
            $data['overwrite'] = Boolean::toString($overwrite);
        }
        $this->schedule[$id] = $data;
        if (null === $this->next || $when < $this->next) {
            $this->next = $when;
        }
        $this->log("SCHEDULED: ACTION={$label} INTERVAL={$seconds} NEXT=".date('Y-m-d H:i:s', $when), LogLevel::DEBUG);

        return $id;
    }

    final public function schedule(
        DateTime $date,
        callable|string $callback,
        array $params = [],
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
            'type' => ScheduleType::NORM,
            'label' => $label,
            'when' => $when,
            'callback' => $callback,
            'args' => $params,
        ];
        if ($tag) {
            $data['tag'] = $tag;
            $data['overwrite'] = Boolean::toString($overwrite);
        }
        $this->schedule[$id] = $data;
        if (null === $this->next || $when < $this->next) {
            $this->next = $when;
        }
        $this->log("SCHEDULED: ACTION={$label} SCHEDULE={$date} NEXT=".date('Y-m-d H:i:s', $when), LogLevel::DEBUG);

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
            'type' => ScheduleType::CRON,
            'label' => $label,
            'when' => $when,
            'callback' => $callback,
            'args' => $arguments,
            'cron' => $cron,
        ];
        if (null === $this->next || $when < $this->next) {
            $this->next = $when;
        }
        $this->log("SCHEDULED: ACTION={$label} CRON=\"{$format}\" NEXT=".date('Y-m-d H:i:s', $when), LogLevel::DEBUG);

        return $id;
    }

    // public function connect(): bool
    // {
    //     if (true === $this->__remote) {
    //         if (!isset($this->config['server'])) {
    //             exit("Warlock server required to run in remote service mode.\n");
    //         }
    //         $headers = [];
    //         $headers['X-WARLOCK-ACCESS-KEY'] = base64_encode($this->config['server']['access_key']);
    //         $headers['X-WARLOCK-CLIENT-TYPE'] = 'service';
    //         $conn = new Socket($protocol, $guid);
    //         $this->log(W_LOCAL, 'Connecting to Warlock server at '.$this->config['server']['host'].':'.$this->config['server']['port']);
    //         if (!$conn->connect($this->config['server']['host'], $this->config['server']['port'], $headers)) {
    //             return false;
    //         }
    //         if (($type = $conn->recv($payload)) === false || 'OK' !== $type) {
    //             return false;
    //         }
    //     } else {
    //         $conn = new Pipe($protocol, $guid);
    //     }

    //     return $conn;
    // }

    final protected function sleep(int $timeout = 0, Status $checkStatus = Status::RUNNING): bool
    {
        if (isset($this->config['checkfile']) && $this->lastCheckfile + $this->config['checkfile'] < time()) {
            $this->lastCheckfile = time();
            if (filemtime($this->serviceFile) !== $this->serviceFileMtime) {
                $this->log->write('Service file has changed, restarting', LogLevel::NOTICE);
                // $this->stop();
            }
        }

        // TODO: Implement a more robust sleep mechanism that checks for service file changes
        // and uses the next scheduled action time from now in seconds to determine the sleep duration.
        $this->__processSchedule();

        return parent::sleep($timeout, $checkStatus);
    }

    /**
     * Initializes the service by subscribing to events and scheduling actions
     * based on the provided configuration.
     *
     * @param array<mixed> $config
     */
    private function initialize(array $config): bool
    {
        $events = $config['subscribe'] ?? [];
        if (is_array($events)) {
            foreach ($events as $eventName => $event) {
                if (is_array($event)) {
                    if (!($action = $event['action'] ?? null)) {
                        continue;
                    }
                    $this->subscribe($eventName, $action, $event['filter'] ?? null);
                } else {
                    $this->subscribe($eventName, $event);
                }
            }
        }
        $schedule = $config['schedule'] ?? [];
        if (is_array($schedule)) {
            foreach ($schedule as $item) {
                if (!(is_array($item) && isset($item['action']))) {
                    continue;
                }
                if (isset($item['interval'])) {
                    $this->interval($item['interval'], $item['action'], $item['args'] ?? null);
                }
                if (isset($item['delay'])) {
                    $this->delay($item['delay'], $item['action'], $item['args'] ?? null);
                }
                if (isset($item['when'])) {
                    $this->cron($item['when'], $item['action'], $item['args'] ?? null);
                }
            }
        }

        return true;
    }
}
