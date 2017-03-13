<?php

namespace Hazaar\Console;

/**
 * Administration short summary.
 *
 * Administration description.
 *
 * @version 1.0
 * @author jamie
 */
class Administration {

    private $passwd;

    private $session_key = 'hazaar-console-user';

    public function __construct(){

        $this->passwd = CONFIG_PATH . DIRECTORY_SEPARATOR . '.passwd';

        if(!file_exists($this->passwd))
            die('Hazaar admin console is currently disabled!');

        session_start();

    }

    public function authenticated(){

        return ake($_SESSION, $this->session_key);

    }

    public function authenticate($username, $password){

        $users = array();

        $lines = explode("\n", trim(file_get_contents($this->passwd)));

        foreach($lines as $line){

            if(!$line)
                continue;

            list($identity, $userhash) = explode(':', $line);

            $users[$identity] = $userhash;

        }

        $credential = trim(ake($users, $username));

        if(strlen($credential) > 0){

            $hash = '';

            if(substr($credential, 0, 6) == '$apr1$'){

                throw new \Exception('APR1-MD5 encoded passwords are not supported!');

            }elseif(substr($credential, 0, 5) == '{SHA}'){

                $hash = '{SHA}' . base64_encode(sha1($password, TRUE));

            }

            if($hash == $credential){

                $_SESSION[$this->session_key] = $username;

                return true;

            }

        }

        return false;

    }

    public function deauth(){

        session_unset();

    }

    public function getNavItems(){

        return array(
            'app' => array(
                'label' => 'Application',
                'items' => array(
                    'index' => 'Overview',
                    'models' => 'Models',
                    'views' => 'Views',
                    'controllers' => 'Controllers'
                )
            )
        );

        /*
        if(class_exists('Hazaar\Cache')){

        $this->view->navitems['cache'] = array(
        'label' => 'Cache',
        'items' => array(
        'settings' => 'Settings'
        )
        );

        }

        if(class_exists('Hazaar\DBI\Adapter')){

        $this->view->navitems['db'] = array(
        'label' => 'Database',
        'items' => array(
        'settings' => 'Settings',
        'schema' => 'Schema Managment',
        'sync' => 'Data Sync'
        )
        );

        if($this->action == 'db_schema'){

        $db = new \Hazaar\DBI\Adapter();

        $current = $db->getSchemaVersion();

        $versions = array('latest' => 'Latest Version') + $db->getSchemaVersions();

        $this->view->current_version = $current . ' - ' . ake($versions, $current, 'missing');

        $this->view->versions = $versions;

        $this->view->latest = $db->isSchemaLatest();

        }

        }

        if(class_exists('Hazaar\Warlock\Control')){

        $this->view->navitems['warlock'] = array(
        'label' => 'Warlock',
        'items' => array(
        'index' => 'Overview',
        'connections' => 'Connections',
        'processes' => 'Processes',
        'services' => 'Services',
        'log' => 'Log File'
        )
        );

        }*/

    }

}