<?php

declare(strict_types=1);

/**
 * @file        Controller/Error.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Controller;

use Hazaar\Application;
use Hazaar\View\Layout;
use Hazaar\XML\Element;

/**
 * Class Dump.
 *
 * This class extends the Diagnostic class and provides various methods to handle and format debugging information.
 * It supports multiple response formats including JSON, XML, text, and HTML.
 *
 * @property mixed                             $data      The data to be dumped.
 * @property float                             $execTime  The execution time of the application.
 * @property bool                              $backtrack Flag to enable or disable backtrace functionality.
 * @property array<array{time:int,data:mixed}> $log       Log entries containing time and data.
 *
 * @method        void          toggleBacktrace(bool $value = true) Toggles the backtrace functionality.
 * @method        void          addLogEntries(array $entries)       Adds log entries to the dump.
 * @method        Response\JSON json(array $dump = [])              Returns a JSON response with the dump data.
 * @method        Response\XML  xmlrpc(array $data = [])            Returns an XML response with the dump data.
 * @method        Response\Text text(array $data = [])              Returns a text response with the dump data.
 * @method        Response\HTML html(array $data = [])              Returns an HTML response with the dump data.
 * @method static string        getSpeedClass(float $execTime)      Returns a string representing the speed class based on execution time.
 *
 * @param mixed       $data        the data to be dumped
 * @param Application $application the application instance
 */
class Dump extends Diagnostic
{
    private mixed $data = null;
    private bool $backtrack = false;

    /**
     * @var array<array{time:int,data:mixed}>
     */
    private array $log = [];

    public function __construct(mixed $data)
    {
        parent::__construct( 'debug');
        $this->data = $data;
    }

    /**
     * Toggles the backtrace functionality.
     *
     * @param bool $value Optional. If true, enables backtrace. If false, disables backtrace. Default is true.
     */
    public function toggleBacktrace(bool $value = true): void
    {
        $this->backtrack = $value;
    }

    /**
     * Adds log entries to the controller.
     *
     * This method accepts an array of log entries and assigns it to the log property.
     *
     * @param array<array{time:int,data:mixed}> $entries an array of log entries to be added
     */
    public function addLogEntries(array $entries): void
    {
        $this->log = $entries;
    }

    /**
     * Generates a JSON response with execution details and data.
     *
     * @param array<mixed> $dump optional array to include additional data in the response
     *
     * @return Response\JSON JSON response containing execution time, status, end time, data, log, and optionally a backtrace
     */
    public function json(array $dump = []): Response\JSON
    {
        $dump['exec'] = $this->execTime;
        $dump['status'] = self::getSpeedClass($this->execTime);
        $dump['end'] = date('c');
        $dump['data'] = $this->data;
        if (count($this->log) > 0) {
            $dump['log'] = $this->log;
        }
        if (true === $this->backtrack) {
            $e = new \Exception('Backtrace');
            $dump['trace'] = $e->getTrace();
        }

        return new Response\JSON($dump, 200);
    }

    /**
     * This is the default XML response for the dump controller.
     *
     * @param array<mixed> $data The data to be displayed in the dump
     */
    public function xmlrpc(array $data = []): Response\XML
    {
        $xml = new Element('xml');
        $app = $xml->add('app');
        $app->add('exec', $this->execTime);
        $app->add('status', self::getSpeedClass($this->execTime));
        $app->add('end', date('c'));
        foreach ($data as $key => $value) {
            $app->add($key, $value);
        }
        $xml->add('data', print_r($this->data, true));
        if (count($this->log) > 0) {
            $log = $xml->add('log');
            foreach ($this->log as $entry) {
                $item = $log->add('entry', $entry['data']);
                $item->attr('time', (string) $entry['time']);
            }
        }
        if (true === $this->backtrack) {
            $e = new \Exception('Backtrace');
            $xml->addFromArray('backtrace', $e->getTrace());
        }

        return new Response\XML($xml);
    }

    /**
     * This is the default text response for the dump controller.
     *
     * @param array<mixed> $data The data to be displayed in the dump
     */
    public function text(array $data = []): Response\Text
    {
        $out = "HAZAAR DUMP\n\n";
        $out .= print_r($this->data, true)."\n\n";
        $out .= "Exec time: {$this->execTime}\n";
        $out .= 'Status: '.self::getSpeedClass($this->execTime)."\n";
        $out .= 'Endtime: '.date('c')."\n";
        foreach ($data as $key => $value) {
            $out .= "{$key}: {$value}\n";
        }
        if (count($this->log) > 0) {
            $out .= "\n\nLOG\n\n";
            foreach ($this->log as $entry) {
                $out .= date('c', $entry['time']).' - '.$entry['data']."\n";
            }
        }
        if (true === $this->backtrack) {
            $out .= "\n\nBACKTRACE\n\n";
            $e = new \Exception('Backtrace');
            $out .= print_r(str_replace('/path/to/code/', '', $e->getTraceAsString()), true);
        }

        return new Response\Text($out."\n", 200);
    }

    /**
     * @detail  This is the default HTML response for the dump controller
     *
     * @param array<mixed> $data The data to be displayed in the dump
     */
    public function html(array $data = []): Response\HTML
    {
        $app = Application::getInstance();
        $view = new Layout('@views/dump');
        $data['env'] = APPLICATION_ENV;
        $data['data'] = $this->data;
        $data['time'] = $app->timer->all();
        $data['log'] = $this->log;
        $view->populate($data);
        if (true === $this->backtrack) {
            $e = new \Exception('Backtrace');
            $view->set('trace', $e->getTrace());
        }

        return $response = new Response\HTML($view->render());
    }

    /**
     * Determines the speed class based on the execution time.
     *
     * @param float $execTime the execution time in milliseconds
     *
     * @return string the speed class, which can be 'excellent', 'good', 'ok', or 'bad'
     */
    public static function getSpeedClass(float $execTime): string
    {
        return ($execTime > 250) ? (($execTime > 500) ? 'bad' : 'ok') : ($execTime < 50 ? 'excellent' : 'good');
    }
}
