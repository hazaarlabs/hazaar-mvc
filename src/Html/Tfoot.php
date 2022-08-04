<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML tfoot class.
 *
 * @detail      Displays an HTML &lt;tfoot&gt; element.
 *
 * @since       1.1
 */
class Tfoot extends Block {

    /**
     * @detail      The HTML tfoot constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = []) {

        parent::__construct('tfoot', $content, $parameters);

    }
    
    public function setRow($fields) {

        $tr = new Tr();

        foreach($fields as $field) {

            if(!$field instanceof Td)
                $field = new Td($field);

            $tr->add($field);

        }

        return parent::set($tr);

    }

    public function add() {

        $args = func_get_args();

        return $this->setRow($args[0]);

    }

}
