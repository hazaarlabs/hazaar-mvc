<?php

namespace Hazaar\Logger;

class Frontend {

    private        $level          = E_NOTICE;

    private        $backend;

    static private $logger;

    static private $message_buffer = array();

    function __construct($level, $backend = NULL, $backend_options = array()) {

        if(! $backend)
            $backend = 'file';

        if(strtolower($backend) == 'mongodb')
            $backend = 'MongoDB';

        if(strtolower($backend) == 'database')
            $backend = 'Database';

        $backend_class = 'Hazaar\\Logger\\Backend\\' . ucfirst($backend);

        if(! $this->backend = new $backend_class($backend_options)) {

            throw new Exception\NoBackend();

        }

        if(is_numeric($level)) {

            $this->level = $level;

        } else {

            $this->level = $this->backend->getLogLevelId($level);

        }

        $this->writeLog('Log level set to: ' . $this->backend->getLogLevelId($this->level), E_ALL);

        if(count($buf = Frontend::$message_buffer) > 0) {

            foreach($buf as $msg)
                $this->writeLog($msg[0], $msg[1]);

        }

    }

    static public function initialise($level = NULL, $backend = NULL, $backend_options = array()) {

        Frontend::$logger = new Frontend($level, $backend, $backend_options);

    }

    static public function destroy() {

        if(Frontend::$logger instanceof Frontend) {

            Frontend::$logger->close();

        }

    }

    static public function write($message, $level = E_NOTICE) {

        if(Frontend::$logger instanceof Frontend) {

            Frontend::$logger->writeLog($message, $level);

        } elseif(is_array(Frontend::$message_buffer)) {

            Frontend::$message_buffer[] = array(
                $message,
                $level
            );

        }

    }

    static public function trace() {

        if(Frontend::$logger instanceof Frontend) {

            Frontend::$logger->backtrace();

        }

    }

    static public function end_purge_mb() {

        Frontend::$message_buffer = NULL;

    }

    public function writeLog($message, $level = E_NOTICE) {

        if(! ($level <= $this->level))
            return NULL;

        if(! $this->backend->can('write_objects')) {

            if(is_array($message) || is_object($message)) {

                $message = preg_replace('/\s+/', ' ', print_r($message, TRUE));

            }

        }

        $this->backend->write($message, $level);

    }

    public function backtrace() {

        if($this->backend->can('write_trace')) {

            $this->backend->trace();

        }

    }

    public function close() {

        $this->backend->postRun();

    }

}

