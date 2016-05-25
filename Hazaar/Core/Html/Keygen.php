<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML keygen class.
 *
 * @detail      Displays an HTML &lt;keygen&gt; element.
 *
 * @since       1.1
 */
class Keygen extends Inline {

    /**
     * @detail      The HTML keygen constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($name, $parameters = array()) {

        $parameters['name'] = $name;

        parent::__construct('keygen', $parameters);

    }

}
