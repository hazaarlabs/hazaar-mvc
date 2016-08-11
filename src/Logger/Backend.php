<?php

namespace Hazaar\Logger;

abstract class Backend implements Backend\_Interface {

    private $options      = array();

    private $capabilities = array();

    function __construct($options) {

        /*
         * Set the options we were given which will overwrite any defaults
         */
        if(! is_array($options))
            $options = array();

        $this->options = $options;

        $this->init();

    }

    public function postRun() {

        //do nothing

    }

    public function setDefaultOption($key, $value) {

        if(! array_key_exists($key, $this->options)) {

            $this->setOption($key, $value);

        }

    }

    public function setOption($key, $value) {

        $this->options[$key] = $value;

    }

    public function getOption($key) {

        if(! array_key_exists($key, $this->options))
            return NULL;

        return $this->options[$key];

    }

    public function hasOption($key) {

        return array_key_exists($key, $this->options);

    }

    public function getLogLevelId($level) {

        $ids = array(
            0         => 'none',
            E_NOTICE  => 'notice',
            E_WARNING => 'warn',
            E_ERROR   => 'error',
            E_ALL     => 'debug'
        );

        if(is_numeric($level)) {

            $ret = $ids[$level];

        } else {

            $name = coalesce(strtolower($level), 'notice');

            $ret = array_search($name, $ids);

        }

        return $ret;

    }

    protected function addCapability($capability) {

        $this->capabilities[] = $capability;

    }

    public function getCapabilities() {

        return $this->capabilities;

    }

    public function can($capability) {

        return in_array($capability, $this->capabilities);

    }

}
