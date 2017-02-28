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

    protected $regex     = '/\$\{([\w\.]*)\}/';

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

        $replaced = array();

        $output = $this->content;

        $params = array_to_dot_notation($params);

        preg_match_all($this->regex, $this->content, $matches);

        foreach($matches[1] as $match) {

            if(in_array($match, $replaced))
                continue;

            if(array_key_exists($match, $params)) {

                $replacement = $params[$match];

            } else {

                $replacement = $this->nullvalue;

            }

            $output = preg_replace('/\$\{' . $match . '\}/', $replacement, $output);

            $replaced[] = $match;

        }

        return $output;

    }

}
