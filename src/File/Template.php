<?php

namespace Hazaar\File;

class Template {

    private $content = NULL;

    private $regex   = '/\$\{(\w*)\}/';

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

        preg_match_all('/\$\{(\w*)\}/', $this->content, $matches);

        foreach($matches[1] as $match) {

            if(in_array($match, $replaced))
                continue;

            if(array_key_exists($match, $params)) {

                $replacement = $params[$match];

            } else {

                $replacement = 'NULL';

            }

            $output = preg_replace('/\$\{' . $match . '\}/', $replacement, $output);

            $replaced[] = $match;

        }

        return $output;

    }

}
