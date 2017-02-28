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

    public function parse($params = array()) {

        $output = $this->content;

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

                }elseif(array_key_exists($match, $params)) {

                    $replacement = $params[$match];

                } else {

                    $replacement = $this->nullvalue;

                }

                $output = preg_replace('/\$\{' . preg_quote($match, '/') . '\}/', $replacement, $output);

                $replaced[] = $match;

            }

        }

        return $output;

    }

}
