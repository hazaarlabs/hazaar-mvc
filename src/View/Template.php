<?php

namespace Hazaar\View;

/**
 * The View\Template class
 *
 * Templates are used to separate view content from application logic.  These templates use a simple
 * tag substitution technique to apply data to templates to generate content.  Data can be applied
 * to templates multiple times (in a loop for example) to generate multiple output content containing
 * different values.  This is useful for tasks such as mail-merges/mass mailouts using a pre-defined
 * email template.
 *
 * Tags are in the format of ${tagname}.  This tag would reference a parameter passed to the parser
 * with the array key value of 'tagname'.  Such as:
 *
 * <code>
 * $tpl->parse(array('tagname' => 'Hello, World!'));
 * </code>
 *
 */
class Template {

    protected $content   = null;

    protected $regex     = '/\$\{(.*?)\}/';

    public    $nullvalue = 'NULL';

    function __construct($content = null){

        if($content)
            $this->content = $content;

    }

    public function setPlaceholderRegex($regex) {

        $this->regex = $regex;

    }

    public function loadFromString($content) {

        $this->content = (string)$content;

    }

    public function loadFromFile($filename) {

        $this->content = file_get_contents($filename);

    }

    public function parse($params = array(), $use_defaults = true) {

        $output = $this->content;

        if($use_defaults){

            $default_params = array(
                '_COOKIE' => $_COOKIE,
                '_ENV' => $_ENV,
                '_GET' => $_GET,
                '_POST' => $_POST,
                '_REQUEST' => $_REQUEST,
                '_SERVER' => $_SERVER,
                'now' => new \Hazaar\Date()
            );

            $params = array_merge($default_params, $params);

        }

        $params = array_to_dot_notation($params);

        $count = 0;

        while(preg_match_all($this->regex, $output, $matches)){

            if($count++ > 5)
                break;

            $replaced = array();

            foreach($matches[1] as $match) {

                if(in_array($match, $replaced))
                    continue;

                if(substr($match, 0, 1) == '"'){ //It's a URL

                    $parts = explode(':', $match, 2); //Split on the first colon

                    $url = ake($parts, 1);

                    //Check if it's a proper URL and if not, make it application relative.
                    if(!preg_match('/^\w+\:\/\//', $url))
                        $url = new \Hazaar\Application\Url($url);

                    $replacement = (string)new \Hazaar\Html\A($url, trim(ake($parts, 0), '"'));

                }else{

                    $args = null;

                    if(strpos($match, ':') !== false){

                        $parts = explode(':', $match, 3);

                        list($key, $modifier, $args) = array(
                            ake($parts, 0),
                            ake($parts, 1),
                            ake($parts, 2)
                        );

                    }else{

                        $key = $match;

                        $modifier = 'string';

                    }

                    if(array_key_exists($key, $params)) {

                        $replacement = $this->setType($params[$key], $modifier, $args);

                    } else {

                        $replacement = $this->nullvalue;

                    }

                }

                $output = preg_replace('/\$\{' . preg_quote($match, '/') . '\}/', $replacement, $output);

                $replaced[] = $match;

            }

        }

        return $output;

    }

    private function setType($value, $type = 'string', $args = null){

        switch($type){

            case 'date':

                if(!$value instanceof \Hazaar\Date)
                    $value = new \Hazaar\Date($value);

                $value = ($args?$value->format($args):(string)$value);

                break;

            case 'string':
            default:

                $value = (string) $value;

        }

        return $value;

    }


}
