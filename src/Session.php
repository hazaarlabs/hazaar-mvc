<?php
/**
 * @file        Hazaar/Session.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
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

    static public  $SESSION_NAME = 'hazaar-session';

    static private $session_id;

    public function __construct($options = array(), $backend = NULL) {

        if(!is_array($options))
            $options = array();
    
        Session::$session_id = ake($_COOKIE, Session::$SESSION_NAME, Session::$session_id);

        if(! Session::$session_id) {

            Session::$session_id = md5(uniqid());

            setcookie(Session::$SESSION_NAME, Session::$session_id, 0, APPLICATION_BASE);

        }

        if(is_string($options)) {

            $options = array('namespace' => $options);

        } else {

            $options['use_pragma'] = FALSE;

            $options['namespace'] = Session::$session_id;

        }

        parent::__construct($backend, $options, Session::$session_id);

    }

}