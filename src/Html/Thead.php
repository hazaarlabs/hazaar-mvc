<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML thead class.
 *
 * @detail      Displays an HTML &lt;thead&gt; element.
 *
 * @since       1.1
 */
class Thead extends Block {

    /**
     * @detail      The HTML thead constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = []) {

        parent::__construct('thead', $content, $parameters);

    }

    public function setRow($fields) {

        $tr = new Tr();

        foreach($fields as $field) {

            if(!$field instanceof Th)
                $field = new Th($field);

            $tr->add($field);

        }

        return parent::set($tr);

    }

    public function add() {

        $args = func_get_args();

        return $this->setRow($args[0]);

    }

}
