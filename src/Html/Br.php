<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML br class.
 *
 * @detail      Displays an HTML &lt;br&gt; element.
 *
 * @since       1.1
 */
class Br extends Inline {

    /**
     * @detail      The HTML br constructor.
     *
     * @since       1.1
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($parameters = []) {

        parent::__construct('br', $parameters);

    }

}
