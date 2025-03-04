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
use Hazaar\Application\FilePath;
use Hazaar\Controller\Response\HTML;
use Hazaar\Controller\Response\JSON;
use Hazaar\Controller\Response\Text;
use Hazaar\Controller\Response\XML;
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
    protected string $shortMessage = '';

    /**
     * The HTTP error code to throw. By default it is 500 Internal Server Error.
     */
    protected int $code = 500;
    protected string $status = 'Internal Error';

    /**
     * @var array<int,string>
     */
    private static ?array $status_codes = null;

    public function __construct($name = 'Error')
    {
        if (!is_array(self::$status_codes)) {
            self::$status_codes = $this->loadStatusCodes();
        }
        parent::__construct($name);
    }

    /**
     * Retrieves the status message corresponding to a given status code.
     *
     * If no status code is provided, it uses the instance's current status code.
     *
     * @param null|int $code The status code to get the message for. If null, the instance's current status code is used.
     *
     * @return null|string the status message corresponding to the provided status code, or null if the status code is not found
     */
    public function getStatusMessage(?int $code = null): ?string
    {
        if (null === $code) {
            $code = $this->code;
        }

        return array_key_exists($code, self::$status_codes) ? self::$status_codes[$code] : null;
    }

    /**
     * Sets the error details based on the provided arguments.
     *
     * This method can handle different types of errors:
     * - If the first argument is an instance of \Throwable, it extracts the error details from the exception.
     * - If the first argument is an array, it assumes it's a shutdown error and extracts the details accordingly.
     * - Otherwise, it assumes it's a standard error and extracts the details from the provided arguments.
     */
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
            $this->responseType = $args[1] ?? Response::TYPE_HTML;
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
        if (!$this->errclass) {
            $this->errclass = self::getErrorTypeString($this->errno);
        }
        $this->status = $this->getStatusMessage($this->code);
    }

    /**
     * Returns a string representation of the given error type.
     *
     * @param int $type The error type constant (e.g., E_ERROR, E_WARNING).
     *
     * @return string the string representation of the error type
     */
    public static function getErrorTypeString(int $type): string
    {
        return match ($type) {
            E_ERROR => 'FATAL ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSING ERROR',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE ERROR',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_ERROR => 'USER ERROR',
            E_USER_WARNING => 'USER WARNING',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'RUNTIME NOTICE',
            E_RECOVERABLE_ERROR => 'CATCHABLE FATAL ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER DEPRECATED',
            default => 'UNKNOWN ERROR',
        };
    }

    /**
     * Retrieves the error message with details about the error.
     *
     * This method constructs a string that includes the error message,
     * the line number where the error occurred, and the file in which
     * the error was found.
     *
     * @return string the detailed error message
     */
    public function getErrorMessage(): string
    {
        return $this->errstr.' on line '.$this->errline.' in file '.$this->errfile;
    }

    /**
     * Retrieves the error message.
     *
     * @return string the error message
     */
    public function getMessage(): string
    {
        return $this->errstr;
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

    /**
     * Cleans the output buffer by ending all active output buffering levels.
     *
     * This method iterates through all active output buffering levels and ends them
     * using `ob_end_clean()`. This ensures that any buffered output is discarded and
     * the output buffer is completely cleaned.
     */
    public function cleanOutputBuffer(): void
    {
        while (count(ob_get_status()) > 0) {
            ob_end_clean();
        }
    }

    /**
     * Outputs an error message and terminates the script.
     *
     * This method prints a formatted error message that includes the error number,
     * the line number where the error occurred, the file in which the error occurred,
     * and the error string. After printing the error message, the script is terminated
     * with the error number as the exit status.
     */
    public function runner(): void
    {
        echo "Runner Error #{$this->errno} at line #{$this->errline} in file {$this->errfile}\n\n{$this->errstr}\n\n";

        exit($this->errno);
    }

    /**
     * Generates a JSON response containing error details.
     *
     * This method constructs an error array with the following structure:
     * - 'ok': A boolean indicating the success status (always false).
     * - 'timestamp': The current timestamp.
     * - 'error': An array containing error details:
     *   - 'type': The error number.
     *   - 'status': The HTTP status code.
     *   - 'str': The error message.
     *   - 'class' (optional): The class where the error occurred (if display_errors is enabled).
     *   - 'line' (optional): The line number where the error occurred (if display_errors is enabled).
     *   - 'file' (optional): The file where the error occurred (if display_errors is enabled).
     * - 'trace' (optional): The debug backtrace (if display_errors is enabled).
     *
     * @return JSON the JSON response containing the error details
     */
    public function json(): JSON
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

        return new JSON($error, $this->code);
    }

    /**
     * Generates an XML fault response.
     *
     * This method constructs an XML structure representing an XML fault response.
     * The response includes details about the error class, error code, status, error
     * message, file, and line number where the error occurred.
     *
     * @return XML the XML fault response
     */
    public function xml(): XML
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

        return new XML($xml);
    }

    /**
     * Generates a detailed text response for an exception.
     *
     * This method constructs a formatted string containing information about the
     * exception, including the environment, timestamp, class, error number, file,
     * line, message, and backtrace. The backtrace includes details about each call
     * in the stack, such as the file, line, class, and function.
     *
     * @return Text a text response containing the formatted exception details
     */
    public function text(): Text
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
                $out .= "{$id} - ".str_pad($call['file'] ?? $this->errfile, 75, ' ', STR_PAD_RIGHT)
                    .' '.str_pad((string) ($call['line'] ?? $this->errline), 4, ' ', STR_PAD_RIGHT)
                    ." {$call['class']}::{$call['function']}\n";
            } else {
                $out .= "{$id} - {$call['function']}\n";
            }
        }
        $out .= "\n";

        return new Text($out, $this->code);
    }

    /**
     * Generates an HTML response for an error.
     *
     * This method creates a new Layout instance for the error view and populates it with
     * various error details such as environment, error type, error code, error message,
     * file, line, class, type, short message, trace, status code, and execution time.
     * It then renders the view and returns it as an HTML response.
     *
     * @return HTML the rendered HTML response containing the error details
     */
    public function html(): HTML
    {
        $app = Application::getInstance();
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
                'short_message' => $this->shortMessage ?? $this->status,
            ],
            'trace' => $this->callstack,
            'code' => $this->code,
            'status' => $this->status,
            'time' => $app->timer->all(5),
        ]);

        return new HTML($view->render(), $this->code);
    }

    /**
     * Loads the status codes from the HTTP_Status.dat file and returns an array of status codes.
     *
     * @return array<int, string> the array of status codes, where the key is the status code and
     *                            the value is the corresponding status message
     */
    private function loadStatusCodes(): array
    {
        $status_codes = [];
        if ($file = Loader::getFilePath(FilePath::SUPPORT, 'HTTP_Status.dat')) {
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
