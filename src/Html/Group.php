<?php

namespace Hazaar\Html;

/**
 * @brief       A container for multiple Element objects
 * 
 * @detail      A standard Block object will have children objects that it will render inside it's own output.  A group
 *              object is similar except that it has no output of it's own.  It merely contains other elements and renders
 *              them individually when it is told to write.
 */
class Group extends Element {

    private $content = [];

    function __construct() {

        foreach(func_get_args() as $arg) {

            $this->add($arg);

        }

    }

    public function renderObject() {

        return implode("\n", $this->content);

    }

    public function add() {

        foreach(func_get_args() as $arg) {

            if(is_array($arg)) {

                foreach($arg as $a)
                    $this->add($a);

            } else {

                $this->content[] = $arg;

            }

        }

    }

}
