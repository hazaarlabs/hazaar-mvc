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

    public function json(): Response\JSON
    {
        $dump = [
            'exec' => $this->exec_time,
            'status' => self::getSpeedClass($this->exec_time),
            'end' => date('c'),
            'data' => $this->data,
        ];

        if (true === $this->backtrack) {
            $e = new \Exception('Backtrace');
            $dump['trace'] = $e->getTrace();
        }

        return new Response\JSON($dump, 200);
    }

    public function xmlrpc(): Response\XML
    {
        $xml = new Element('xml');
        $app = $xml->add('app');
        $app->add('exec', $this->exec_time);
        $app->add('status', self::getSpeedClass($this->exec_time));
        $app->add('end', date('c'));
        $xml->add('data', print_r($this->data, true));
        if (true === $this->backtrack) {
            $e = new \Exception('Backtrace');
            $xml->addFromArray('backtrace', $e->getTrace());
        }

        return new Response\XML($xml);
    }

    public function text(): Response\Text
    {
        $out = "HAZAAR DUMP\n\n";
        $out .= print_r($this->data, true)."\n\n";
        $out .= "Exec time: {$this->exec_time}\n";
        $out .= 'Status: '.self::getSpeedClass($this->exec_time)."\n";
        $out .= 'Endtime: '.date('c')."\n";
        if (true === $this->backtrack) {
            $out .= "\n\nBACKTRACE\n\n";
            $e = new \Exception('Backtrace');
            $out .= print_r(str_replace('/path/to/code/', '', $e->getTraceAsString()), true);
        }

        return new Response\Text($out."\n", 200);
    }

    public function html(): Response\HTML
    {
        $view = new Layout('@views/dump');
        $view->populate([
            'env' => APPLICATION_ENV,
            'data' => $this->data,
            'time' => $this->application->timer->all(),
        ]);

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
