<?php
/**
 * @file        Hazaar/Application/Request/Cli.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
 
namespace Hazaar\Application\Request;

class Cli extends \Hazaar\Application\Request {

    private $options = [];
    
    static private $opt;

    function init($args) {

        $this->params = $args;

    }

    public function setOptions($options){

        $this->options = $options;

    }

    public function getOptions(){

        if(!self::$opt){

            self::$opt = [0 => '', 1 => []];

            foreach($this->options as $name => $o){

                if($o[0]) self::$opt[0] .= $o[0] . (is_string($o[2]) ? ':' : '');

                if($o[1]) self::$opt[1][] = $o[1] . (is_string($o[2]) ? ':' : '');

            }

        }

        $ops = getopt(self::$opt[0], self::$opt[1]);

        $options = [];

        foreach($this->options as $name => $o){

            $s = $l = false;

            $sk = $lk = null;

            if(($o[0] && ($s = array_key_exists($sk = rtrim($o[0], ':'), $ops))) || ($o[1] && ($l = array_key_exists($lk = rtrim($o[1], ':'), $ops))))
                $options[$name] = is_string($o[2]) ? ($s ? $ops[$sk] : $ops[$lk]) : true;

        }

        if(ake($options, 'help') === true)
            return $this->showHelp();

        return $options;

    }

    public function showHelp(){

        $script = basename($_SERVER['SCRIPT_FILENAME']);

        $msg = "Syntax: $script [options]\nOptions:\n";
        
        foreach($this->options as $o){

            $avail = [];

            if($o[0]) $avail[] = '-' . $o[0] . (is_string($o[2]) ? ' ' . $o[2] : '');

            if($o[1]) $avail[] = '--' . $o[1] . (is_string($o[2]) ? '=' . $o[2] : '');

            $msg .= '  ' . implode(', ', $avail) . $o[3] . "\n";

        }

        echo $msg;

        return 0;

    }

}