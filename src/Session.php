<?php
/**
 * @file        Hazaar/Session.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

/**
 * @brief       Session class
 *
 * @detail      Sessions make use of the Hazaar\Cache class but they create a unique session ID so stored data is not
 *              shared between user sessions.  If you want to store data that can be shared then use the Hazaar\Cache
 *              classes directly.
 *
 * @since       2.0.1
 */
class Session extends \Hazaar\Cache {

    private $session_name = 'hazaar-session';

    private $session_id;

    private $session_init = false;

    public function __construct($options = [], $backend = null) {

        $options = new \Hazaar\Map([
                'hash_algorithm' => 'ripemd128',
                'session_name' => 'hazaar-session'
        ], $options);

        if($options->has('session_name'))
            $this->session_name = $options->get('session_name');

        if($options->has('session_id'))
            $this->session_id = $options->get('session_id');

        if(!($this->session_id || ($this->session_id = ake($_COOKIE, $this->session_name))))
            $this->session_id =  $options->has('session_id') ? $options->get('session_id') : hash($options->get('hash_algorithm'), uniqid());
        else $this->session_init = true;

        $options->use_pragma = false;

        $options->keepalive = true;

        parent::__construct($backend, $options, $this->session_id);

        if(!$this->backend->can('keepalive'))
            throw new \Exception('The currently selected cache backend, ' . get_class($this->backend) . ', does not support the keepalive feature which is required by the ' . __CLASS__ . ' class.  Please choose a caching backend that supports the keepalive feature.');

    }

    public function set($key, $value, $timeout = NULL) {

        if($this->session_init !== true && strpos(php_sapi_name(), 'cli') === false){

            setcookie($this->session_name, $this->session_id, 0,  \Hazaar\Application::path());

            $this->session_init = true;

        }

        return parent::set($key, $value, $timeout);

    }

    public function clear(){

        if(!parent::clear())
            return false;

        if(ake($_COOKIE, $this->session_name) === $this->session_id)
            setcookie($this->session_name, '', time() - 3600,  \Hazaar\Application::path());

        return true;

    }

}