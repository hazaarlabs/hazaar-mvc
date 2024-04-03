<?php

namespace Hazaar\Logger;

class Frontend {

    private        $level          = E_ERROR;

    private        $backend;

    static private $logger;

    static private $message_buffer = [];

    function __construct($level, $backend = NULL, $backend_options = []) {

        if(! $backend)
            $backend = 'file';

        if(strtolower($backend) == 'mongodb')
            $backend = 'MongoDB';

        if(strtolower($backend) == 'database')
            $backend = 'Database';

        $backend_class = 'Hazaar\\Logger\\Backend\\' . ucfirst($backend);

        if(!class_exists($backend_class))
            throw new Exception\NoBackend();

        if(!($this->backend = new $backend_class($backend_options)))
            throw new Exception\NoBackend();

        if(is_numeric($level)) {

            $this->level = $level;

        } elseif(($this->level = $this->backend->getLogLevelId($level)) === false){

            $this->level = E_ERROR;

        }

        $buf = Frontend::$message_buffer;

        if(is_array($buf) && count($buf) > 0) {

            foreach($buf as $msg)
                $this->writeLog($msg[0], $msg[1]);

        }

        Frontend::$message_buffer = [];

    }

    static public function initialise(\Hazaar\Map $config) {
          
        if($config->enable !== true)
            return;
        
        if(($options = $config->get('options')) instanceof \Hazaar\Map)
            $options = $options->toArray();

        Frontend::$logger = new Frontend($config->get('level'), $config->get('backend'), $options);

        eval('class log extends \Hazaar\Logger\Frontend{};');

    }

    static public function destroy() {

        if(Frontend::$logger instanceof Frontend)
            Frontend::$logger->close();

    }

    static public function write($tag, $message, $level = E_NOTICE, $request = null) {

        if(Frontend::$logger instanceof Frontend) {

            Frontend::$logger->writeLog($tag, $message, $level, $request);

        } elseif(is_array(Frontend::$message_buffer)) {

            Frontend::$message_buffer[] = [
                $tag,
                $message,
                $level,
                $request
            ];

        }

    }

    /**
     * Log an ERROR message
     */
    static public function e($tag, $message, $request = null){

        Frontend::write($tag, $message, LOG_ERR, $request);

    }

    /**
     * Log a WARNING message
     */
    static public function w($tag, $message, $request = null){

        Frontend::write($tag, $message, LOG_WARNING, $request);

    }

    /**
     * Log a NOTICE message
     */
    static public function n($tag, $message, $request = null){

        Frontend::write($tag, $message, LOG_NOTICE, $request);

    }

    /**
     * Log a INFO message
     */
    static public function i($tag, $message, $request = null){

        Frontend::write($tag, $message, LOG_INFO, $request);

    }

    /**
     * Log a DEBUG message
     */
    static public function d($tag, $message, $request = null){

        Frontend::write($tag, $message, LOG_DEBUG, $request);

    }

    static public function trace() {

        if(Frontend::$logger instanceof Frontend)
            Frontend::$logger->backtrace();

    }

    static public function end_purge_mb() {

        Frontend::$message_buffer = NULL;

    }

    public function writeLog($tag, $message, $level = E_NOTICE, $request = NULL) {

        if(! ($level <= $this->level))
            return NULL;

        if(! $this->backend->can('write_objects')) {

            if(is_array($message) || is_object($message))
                $message = "OBJECT DUMP:" . LINE_BREAK . preg_replace('/\n/', LINE_BREAK, print_r($message, TRUE));

        }

        $this->backend->write($tag, $message, $level, $request);

    }

    public function backtrace() {

        if($this->backend->can('write_trace'))
            $this->backend->trace();

    }

    public function close() {

        $this->backend->postRun();

    }

}
