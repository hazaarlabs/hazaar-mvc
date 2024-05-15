<?php

namespace Hazaar\Mail;

use Hazaar\Map;

abstract class Transport implements Transport\_Interface
{
    protected $options;
    protected $dsn = [];

    final public function __construct($options)
    {
        if (!$options instanceof Map) {
            $options = new Map($options);
        }

        $this->options = $options;

        $this->init($options);
    }

    /**
     * Enables ALL Delivery Status Notification types.
     */
    public function enableDSN()
    {
        $this->dsn = ['success', 'failure', 'delay'];
    }

    /**
     * Disable ALL Delivery Status Notifications.
     */
    public function disableDSN()
    {
        $this->dsn = ['never'];
    }

    /**
     * Enables SUCCESS Delivery Status Notification types.
     */
    public function enableDSNSuccess()
    {
        $this->resetDSN();

        if (!in_array('success', $this->dsn)) {
            $this->dsn[] = 'success';
        }
    }

    /**
     * Enables SUCCESS Delivery Status Notification types.
     */
    public function enableDSNFailure()
    {
        $this->resetDSN();

        if (!in_array('failure', $this->dsn)) {
            $this->dsn[] = 'failure';
        }
    }

    /**
     * Enables SUCCESS Delivery Status Notification types.
     */
    public function enableDSNDelay()
    {
        $this->resetDSN();

        if (!in_array('delay', $this->dsn)) {
            $this->dsn[] = 'delay';
        }
    }

    protected function init($options)
    {
        return true;
    }
}
