<?php

namespace Hazaar\Logger\Backend;

class File extends \Hazaar\Logger\Backend {

    private $h;

    public function init() {

        $this->addCapability('write_trace');

        $this->setDefaultOption('logfile', '/tmp/hazaar.log');

        $this->setDefaultOption('write_ip', TRUE);

        $this->setDefaultOption('write_timestamp', TRUE);

        $this->setDefaultOption('write_uri', TRUE);

        if(($this->h = fopen($this->getOption('logfile'), 'a')) == FALSE) {

            throw new Exception\OpenLogFileFailed($this->getOption('logfile'));

        }

    }

    public function postRun() {

        if($this->h) {

            fclose($this->h);

        }

    }

    public function write($message, $level = E_NOTICE) {

        $remote = $_SERVER['REMOTE_ADDR'];

        $line = array();

        if($this->getOption('write_ip'))
            $line[] = $remote;

        if($this->getOption('write_timestamp'))
            $line[] = '[ ' . date('Y-m-d H:i:s') . ' ]';

        $line[] = str_pad(strtoupper($this->getLogLevelId($level)), 6, ' ', STR_PAD_RIGHT) . ' |';

        if($this->getOption('write_uri'))
            $line[] = $_SERVER['REQUEST_URI'] . ' -';

        $line[] = $message;

        fwrite($this->h, implode(' ', $line) . "\n");

    }

    public function trace() {

        ob_start();

        debug_print_backtrace();

        $trace = ob_get_clean();

        fwrite($this->h, $trace);

    }

}

