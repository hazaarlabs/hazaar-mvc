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
 * @property bool                              $backtrack Flag to enable or disable backtrace functionality.
 * @property array<array{time:int,data:mixed}> $log       Log entries containing time and data.
 *
 * @method void          toggleBacktrace(bool $value = true)  Toggles the backtrace functionality.
 * @method void          addLogEntries(array<mixed> $entries) Adds log entries to the dump.
 * @method Response\JSON json(array<mixed> $dump = [])        Returns a JSON response with the dump data.
 * @method Response\XML  xmlrpc(array<mixed> $data = [])      Returns an XML response with the dump data.
 * @method Response\Text text(array<mixed> $data = [])        Returns a text response with the dump data.
 * @method Response\HTML html(array<mixed> $data = [])        Returns an HTML response with the dump data.
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

    /**
     * Constructor.
     *
     * @param array<mixed> $data the data items to be dumped
     */
    public function __construct(array $data)
    {
        parent::__construct('debug');
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
        $application = Application::getInstance();
        foreach ($application->timer->all() as $timer => $sec) {
            $dump[$timer] = $sec;
        }
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
    public function xml(array $data = []): Response\XML
    {
        $application = Application::getInstance();
        $xml = new Element('xml');
        $app = $xml->add('app');
        foreach ($application->timer->all() as $timer => $sec) {
            $app->add($timer, $sec);
        }
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
        $application = Application::getInstance();
        $out = "HAZAAR DUMP\n\n";
        $out .= "TIMERS\n\n";
        foreach ($application->timer->all() as $timer => $sec) {
            $out .= "{$timer}: {$sec}\n";
        }
        $out .= "\nCONTEXT\n\n";
        foreach ($data as $key => $value) {
            $out .= "{$key}: {$value}\n";
        }
        $out .= "\nDATA\n\n";
        foreach ($this->data as $dataItem) {
            $out .= print_r($dataItem, true)."\n";
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
        $application = Application::getInstance();
        $view = new Layout('@views/dump');
        $data['env'] = APPLICATION_ENV;
        $data['data'] = $this->data;
        $data['time'] = $application->timer->all();
        $data['log'] = $this->log;
        $view->populate($data);
        if (true === $this->backtrack) {
            $e = new \Exception('Backtrace');
            $view->set('trace', $e->getTrace());
        }

        return new Response\HTML($view->render());
    }
}
