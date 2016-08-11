<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML colgroup class.
 *
 * @detail      Displays an HTML &lt;colgroup&gt; element.
 *
 * @since       1.1
 */
class Colgroup extends Block {

    /**
     * @detail      The HTML colgroup constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($parameters = array()) {

        parent::__construct('colgroup', null, $parameters);

    }

    public function col($parameters = array()) {

        $col = new Col($parameters);

        return $this;

    }

}
