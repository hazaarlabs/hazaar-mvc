<?php

declare(strict_types=1);

/**
 * @file        Controller/Error.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Controller;

use Hazaar\Application\Router;
use Hazaar\View\Layout;
use Hazaar\XML\Element;

/**
 * @brief Basic controller class
 *
 * @detail This controller does basic stuff
 */
class Dump extends Diagnostic
{
    private mixed $data = null;
    private float $exec_time = -1;
    private bool $backtrack = false;

    /**
     * @var array<array{time:int,data:mixed}>
     */
    private array $log = [];

    public function __construct(mixed $data, Router $router)
    {
        parent::__construct($router, 'debug');
        $this->exec_time = $this->application->GLOBALS['hazaar']['exec_start'];
        $this->data = $data;
    }

    public function toggleBacktrace(bool $value = true): void
    {
        $this->backtrack = $value;
    }

    /**
     * @param array<array{time:int,data:mixed}> $entries
     */
    public function addLogEntries(array $entries): void
    {
        $this->log = $entries;
    }

    /**
     * This is the default JSON response for the dump controller.
     *
     * @param array<mixed> $dump The data to be displayed in the dump
     */
    public function json(array $dump = []): Response\JSON
    {
        $dump['exec'] = $this->exec_time;
        $dump['status'] = self::getSpeedClass($this->exec_time);
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
        $app->add('exec', $this->exec_time);
        $app->add('status', self::getSpeedClass($this->exec_time));
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
        $out .= "Exec time: {$this->exec_time}\n";
        $out .= 'Status: '.self::getSpeedClass($this->exec_time)."\n";
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
        $view = new Layout('@views/dump');
        $data['env'] = APPLICATION_ENV;
        $data['data'] = $this->data;
        $data['time'] = $this->application->timer->all();
        $data['log'] = $this->log;
        $view->populate($data);
        if (true === $this->backtrack) {
            $e = new \Exception('Backtrace');
            $view->set('trace', $e->getTrace());
        }

        return $response = new Response\HTML($view->render());
    }

    public static function getSpeedClass(float $exec_time): string
    {
        return ($exec_time > 250) ? (($exec_time > 500) ? 'bad' : 'ok') : ($exec_time < 50 ? 'excellent' : 'good');
    }
}
