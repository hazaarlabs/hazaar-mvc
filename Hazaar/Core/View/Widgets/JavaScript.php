<?php

namespace Hazaar\View\Widgets;

class JavaScript extends \Hazaar\View\ViewableObject {

    private $value;

    private $argdef;

    private $anon = false;

    function __construct($value, $argdef = array(), $anon = false) {

        $this->value = $value;

        $this->setArgs($argdef);

        $this->anon = $anon;

    }

    public function setArgs($argdef = array()) {

        if(!is_array($argdef))
            $argdef = array($argdef);

        $this->argdef = $argdef;

    }

    /**
     * @detail      Renders the function to a string
     *
     * @param       bool $anon Use an anonymous function when rendering.  Overrides $this->anon().  Useful for
     *              rendering anonymous functions when they are inside a specific object such as JSONObject.
     *
     * @return      string
     */
    public function renderObject($anon = false) {
        
        $code = $this->renderChildObject($this->value);

        if(substr($code, 0, 1) == '!') {

            return substr($code, 1);

        } else {

            if($anon || $this->anon) {

                $arglist = implode(', ', $this->argdef);

                return 'function(' . $arglist . '){ ' . $code . ' }';

            }

        }

        return $code;

    }
    
    private function renderChildObject($obj){
        
        if(is_array($obj)){
            
            $code = array();
            
            foreach($obj as $child) $code[] = $this->renderChildObject($child);
            
            return implode('; ', $code);
            
        }
        
        return (string)$obj;
    
    }

    /**
     * @detail      Turns off the the use of an anonymous function container
     *
     * @return      \\Hazaar\\Widgets\\JavaScript
     */
    public function anon($toggle = true) {

        $this->anon = $toggle;

        return $this;

    }

}
