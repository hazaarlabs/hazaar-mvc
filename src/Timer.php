<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Timer.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

/**
 * Timer class for measuring how long things take.
 *
 * The timer class can be used to time one or more events and returns how long it took in milliseconds.
 *
 * ## Application Timer
 *
 * The Hazaar\Application class has a built-in timer for measuring the performance of your application automatically.  By default
 * this timer is disabled.  See the `app.timer` setting in [Config Directives](http://scroly.io/hazaarmvc/latest/reference/configs.md).
 */
class Timer
{
    /**
     * Array of timers.
     *
     * This is an associative array of timers.  The key is the name of the timer and the value is an array with the
     *
     * - start: The time the timer was started
     * - stop: The time the timer was stopped
     *
     * If the timer is currently running, the stop key will not exist.
     *
     * @var array<string,array<string,float>>
     */
    private array $timers = [];

    /**
     * The name of the last timer.  Used in checkpointing.
     */
    private string $last;

    /**
     * Timer Precision.
     *
     * This sets the precision of the output.  By default it is 2 which means output is 1/10th of a millisecond.
     */
    private int $precision = 0;

    /**
     * Timer class constructor.
     *
     * The timer class has an implicit timer that is always active called the 'global' timer.  This is simply
     * used to record how long the timer class itself has been active.
     *
     * @param int $precision The precision to use when returning timer values. Defaults to 2.
     */
    public function __construct(int $precision = 2)
    {
        $this->precision = $precision;
        $this->start('global', HAZAAR_EXEC_START);
    }

    /**
     * Magic toString method.
     *
     * This will return a string representation of the current state of the default timer object.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->get();
    }

    /**
     * Start a new timer.
     *
     * A timer is nothing more than a named point in time.  When a timer starts, no long running code is executed
     * or anything like that and simply the current system time in microseconds is recorded against the timer name.
     * This allows us to later query that timer name and return the difference which will give you the number
     * of milliseconds between two points in time.
     *
     * @param string $name a name for the timer
     * @param float  $when Optionally allow the start time to be overriden.  Defaults to PHP's microtime(true) value.
     */
    public function start(string $name = 'default', ?float $when = null): void
    {
        if (!$when) {
            $when = microtime(true);
        }
        $this->timers[$name] = ['start' => $when];
        $this->last = $name;
    }

    /**
     * Stop a currently running timer and return it's value.
     *
     * @param mixed $name The name of the timer to stop.  If the timer does not exist an exception is thrown.
     *
     * @return float the difference, in milliseconds, between when the timer was started and when it was stopped
     *
     * @throws Exception
     */
    public function stop($name = 'default')
    {
        if (!array_key_exists($name, $this->timers)) {
            throw new Exception("Error trying to stop non-existent timer '{$name}'.");
        }
        if ('global' == $name) {
            throw new Exception('You can not stop the global timer!');
        }
        if (!array_key_exists('stop', $this->timers[$name])) {
            $this->timers[$name]['stop'] = microtime(true);
        }

        return $this->get($name);
    }

    /**
     * Create a timer checkpoint.
     *
     * Simply put, this will automatically stop the last timer and start a new one in one function call.
     *
     * @param mixed $name the name of the new timer
     */
    public function checkpoint($name): void
    {
        if ($this->last) {
            $this->stop($this->last);
            $this->start($name);
        }
    }

    /**
     * Get the current state of a timer.
     *
     * If a timer is currently running, then it's value will be the difference between when it started
     * and 'now'.  If a timer has stopped, it's value will be the difference between when it was started and when
     * it stopped.
     *
     * @param string $name      The name of the timer to stop.  If the timer does not exist an exception is thrown.
     * @param int    $precision The precision of the returned value.  If not specified the precision used in the constructor is used.
     */
    public function get(string $name = 'default', ?int $precision = null): float
    {
        if (!array_key_exists($name, $this->timers)) {
            throw new Exception("Unable to return current value of non-existent timer '{$name}'.");
        }
        $timer = $this->timers[$name];
        $start = $timer['start'];
        $stop = (array_key_exists('stop', $timer) ? $timer['stop'] : microtime(true));

        return round(($stop - $start) * 1000, (null !== $precision) ? $precision : $this->precision);
    }

    /**
     * Get an array of all timers and their current state.
     *
     * @param int $precision The precision of the returned values.  If not specified the precision used in the constructor is used.
     *
     * @return array<float>
     */
    public function all(?int $precision = null): array
    {
        $results = [];
        foreach (array_keys($this->timers) as $name) {
            $results[$name] = $this->get($name, $precision);
        }

        return $results;
    }
}
