<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML hr class.
 *
 * @detail      Displays an HTML &lt;hr&gt; element.
 *
 * @since       1.1
 */
class Hr extends Inline {

    /**
     * @detail      The HTML hr constructor.
     *
     * @since       1.1
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($parameters = []) {

        parent::__construct('hr', $parameters);

    }

}
