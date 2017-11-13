<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML ins class.
 *
 * @detail      Displays an HTML &lt;ins&gt; element.
 *
 * @since       1.1
 */
class jQuery extends Script {

    static private $instance = null;

    private $pre_exec = array();

    private $exec = array();

    private $post_exec = array();

    /**
     * @detail      The jQuery constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null) {

        parent::__construct($content);

        jQuery::$instance = $this;

    }

    static public function getInstance($content = null) {

        if(!($instance = jQuery::$instance)) {

            $instance = new jQuery($content);

        }

        return $instance;

    }

    /**
     * @detail      Escape a jQuery selector if it contains characters that require escaping.
     *
     * @return      string The selector with any required escape characters.
     */
    public function escapeSelector($value) {

        return preg_replace('/([\[\]])/', "\\\\\\\\\\1", $value);

    }

    public function preExec($code) {

        $this->pre_exec[] = $code;

    }

    public function exec($code, $priority = 0) {

        settype($priority, 'int');

        $this->exec[$priority][] = $code;

    }

    public function postExec($code) {

        $this->post_exec[] = $code;

    }

    public function post() {

        if(count($this->pre_exec) > 0 || count($this->exec) > 0 || count($this->post_exec) > 0) {

            $out = '$(document).ready(function(){';

            $out .= implode("\n", $this->pre_exec);

            krsort($this->exec);

            foreach($this->exec as $priority => $exec)
                $out .= implode("\n", $exec);

            $out .= implode("\n", $this->post_exec);

            $out .= '});';

            $script = new \Hazaar\Html\Script($out);

            return $script;

        }

        return null;

    }

}
