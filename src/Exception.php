<?php

namespace Hazaar;

/**
 * Enhanced Exception class for Hazaar MVC.
 *
 * This exception class contains some useful additions to the standard [[Exception]] class.  Firstly,
 * it supports a new "short message" that will be used when the full error display is disabled, such
 * as it would be in a production environment.  This allows a more simplified message to be displayed
 * to users that can not really be used for debugging, but looks nicer.
 *
 * This class also allows the line, file, and original message to be updated.  This is used in templates
 * when parsing the template throws an error, we are able to set the line and file to that of the template.
 */
class Exception extends \Exception
{
    protected string $name = 'Exception';
    protected ?string $shortMessage;

    /**
     * \Hazaar\Exception constructor.
     *
     * @param string $message       The exception message
     * @param int    $code          The error code, also used as the HTTP response code
     * @param string $shortMessage an optional short message to display when full error display is disabled
     */
    public function __construct(string $message, int $code = 500, string $shortMessage = null)
    {
        parent::__construct($message, $code);
        $this->shortMessage = $shortMessage;
    }

    /**
     * Returns the name of the Exception class in the case it has been extended.
     */
    public function getName(): string
    {
        if (!$this->name) {
            return preg_replace('/Hazaar\\\\Exception\\\\/', '', get_class($this));
        }

        return $this->name;
    }

    /**
     * Returns the short message.
     */
    public function getShortMessage(): string
    {
        if ($this->shortMessage) {
            return $this->shortMessage;
        }
        $message = null;
        if (404 == $this->code) {
            $message = 'That page does not exist!';
        } elseif (500 == $this->code) {
            $message = 'Something seems to be broken!';
        }

        return $message;
    }

    /**
     * Set the error code.
     */
    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    /**
     * Set the error message.
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * Set the file name that the exception occurred in.
     */
    public function setFile(string $file): void
    {
        $this->file = $file;
    }

    /**
     * Set the line number that threw the exception.
     */
    public function setLine(int $line): void
    {
        $this->line = $line;
    }
}
