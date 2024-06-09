<?php

declare(strict_types=1);

/**
 * @file        Controller/Error.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Controller;

use Hazaar\Application\Request\HTTP;
use Hazaar\Application\Router;
use Hazaar\Loader;
use Hazaar\View\Layout;
use Hazaar\XML\Element;

define('ERR_TYPE_ERROR', 0);
define('ERR_TYPE_EXCEPTION', 1);
define('ERR_TYPE_SHUTDOWN', 2);

/**
 * @brief Basic controller class
 *
 * @detail This controller does basic stuff
 */
class Error extends Diagnostic
{
    protected int $type = ERR_TYPE_ERROR;
    protected int|string $errno = '';
    protected string $errstr = '';
    protected string $errfile = '';
    protected int $errline = 0;
    protected string $errtype = '';
    protected string $errclass = '';

    /**
     * @var array<mixed>
     */
    protected array $callstack = [];
    protected string $short_message = '';
    // The HTTP error code to throw. By default it is 500 Internal Server Error.
    protected int $code = 500;
    protected string $status = 'Internal Error';
    protected string $responseType = 'html';

    /**
     * @var array<int,string>
     */
    private static ?array $status_codes = null;

    public function __construct(Router $router, $name = 'Error')
    {
        if (!is_array(self::$status_codes)) {
            self::$status_codes = $this->loadStatusCodes();
        }
        parent::__construct($router, $name);
    }

    public function getStatusMessage(?int $code = null): ?string
    {
        if (null === $code) {
            $code = $this->code;
        }

        return array_key_exists($code, self::$status_codes) ? self::$status_codes[$code] : null;
    }

    public function setError(): void
    {
        $args = func_get_args();
        if ($args[0] instanceof \Throwable) {
            $e = $args[0];
            $this->type = ERR_TYPE_EXCEPTION;
            $this->errno = $e->getCode();
            $this->errstr = $e->getMessage();
            $this->errfile = $e->getFile();
            $this->errline = $e->getLine();
            $this->errclass = get_class($e);
            $this->callstack = $e->getTrace();
            $code = $e->getCode();
            if (is_int($code)) {
                $this->code = $code;
                if (!array_key_exists($this->code = $e->getCode(), self::$status_codes)) {
                    $this->code = 500;
                }
            }
        } elseif (is_array($args[0])) {
            $this->type = ERR_TYPE_SHUTDOWN;
            $this->errno = $args[0]['type'];
            $this->errstr = $args[0]['message'];
            $this->errfile = $args[0]['file'];
            $this->errline = $args[0]['line'];
            $this->errtype = 'ERROR::FATAL';
            $this->callstack = $args[1];
        } else {
            $this->type = ERR_TYPE_ERROR;
            $this->errno = $args[0];
            $this->errstr = $args[1];
            $this->errfile = $args[2];
            $this->errline = $args[3];
            $this->callstack = $args[4];
            if (!array_key_exists($this->code = $args[0], self::$status_codes)) {
                $this->code = 500;
            }
        }
        $this->status = $this->getStatusMessage($this->code);
    }

    public function getMessage(): string
    {
        return $this->errstr;
    }

    public function getErrorMessage(): string
    {
        return $this->errstr.' on line '.$this->errline.' in file '.$this->errfile;
    }

    /**
     * Get the error code.
     *
     * @return array<int,string>
     */
    public function getTrace(): array
    {
        return $this->callstack;
    }

    public function cleanOutputBuffer(): void
    {
        while (count(ob_get_status()) > 0) {
            ob_end_clean();
        }
    }

    public function runner(): void
    {
        echo "Runner Error #{$this->errno} at line #{$this->errline} in file {$this->errfile}\n\n{$this->errstr}\n\n";

        exit($this->errno);
    }

    public function json(): Response\JSON
    {
        $error = [
            'ok' => false,
            'timestamp' => time(),
            'error' => [
                'type' => $this->errno,
                'status' => $this->status,
                'str' => $this->errstr,
            ],
        ];
        if (ini_get('display_errors')) {
            $error['error']['class'] = $this->errclass;
            $error['error']['line'] = $this->errline;
            $error['error']['file'] = $this->errfile;
            $error['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        return new Response\JSON($error, $this->code);
    }

    public function xmlrpc(): Response\XML
    {
        $xml = new Element('xml');
        $struct = $xml->add('fault')->add('value')->add('struct');
        $class = $struct->add('member');
        $class->add('name', 'faultClass');
        $class->add('value')->add('string', $this->errclass);
        $code = $struct->add('member');
        $code->add('name', 'faultCode');
        $code->add('value')->add('int', (string) $this->errno);
        $status = $struct->add('member');
        $status->add('name', 'faultString');
        $status->add('value')->add('string', $this->status);
        $string = $struct->add('member');
        $string->add('name', 'faultString');
        $string->add('value')->add('string', $this->errstr);
        $file = $struct->add('member');
        $file->add('name', 'faultFile');
        $file->add('value')->add('string', $this->errfile);
        $line = $struct->add('member');
        $line->add('name', 'faultLine');
        $line->add('value')->add('int', (string) $this->errline);

        return new Response\XML($xml);
    }

    public function text(): Response\Text
    {
        $out = "*****************************\n\tEXCEPTION\n*****************************\n\n";
        $out .= "Environment:\t".APPLICATION_ENV."\n";
        $out .= "Timestamp:\t".date('c')."\n";
        $out .= "Class:\t\t".$this->errclass."\n";
        if ($this->errno > 0) {
            $out .= "Error:\t\t#".$this->errno."\n";
        }
        $out .= "File:\t\t".$this->errfile."\n";
        $out .= "Line:\t\t".$this->errline."\n";
        $out .= "Message:\t".$this->errstr."\n";
        $out .= "Backtrace:\n\n";
        foreach ($this->callstack as $id => $call) {
            if (array_key_exists('class', $call)) {
                $out .= "{$id} - ".str_pad(ake($call, 'file', $this->errfile), 75, ' ', STR_PAD_RIGHT).' '.str_pad((string) ake($call, 'line', $this->errline), 4, ' ', STR_PAD_RIGHT)." {$call['class']}::{$call['function']}\n";
            } else {
                $out .= "{$id} - {$call['function']}\n";
            }
        }
        $out .= "\n";

        return new Response\Text($out, $this->code);
    }

    public function html(): Response\HTML
    {
        $view = new Layout('@views/error');
        $view->populate([
            'env' => APPLICATION_ENV,
            'type' => $this->type,
            'err' => [
                'code' => $this->errno,
                'message' => $this->errstr,
                'file' => $this->errfile,
                'line' => $this->errline,
                'class' => $this->errclass,
                'type' => $this->errtype,
                'short_message' => ($this->short_message ? $this->short_message : $this->status),
            ],
            'trace' => $this->callstack,
            'code' => $this->code,
            'status' => $this->status,
            // @phpstan-ignore-next-line
            'time' => (microtime(true) - HAZAAR_INIT_START) * 1000,
        ]);

        return new Response\HTML($view->render(), $this->code);
    }

    /**
     * Loads the status codes from the HTTP_Status.dat file and returns an array of status codes.
     *
     * @return array<int, string> the array of status codes, where the key is the status code and the value is the corresponding status message
     */
    private function loadStatusCodes(): array
    {
        $status_codes = [];
        if ($file = Loader::getFilePath(FILE_PATH_SUPPORT, 'HTTP_Status.dat')) {
            $h = fopen($file, 'r');
            while ($line = fgets($h)) {
                if (preg_match('/^(\d*)\s(.*)$/', $line, $matches)) {
                    $status_codes[(int) $matches[1]] = $matches[2];
                }
            }
        }

        return $status_codes;
    }
}
