<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML textarea class.
 *
 * @detail      Displays an HTML &lt;textarea&gt; element.
 *
 * @since       1.1
 */
class Textarea extends Block {

    /**
     * @detail      The HTML textarea constructor.
     *
     * @since       1.1
     *
     * @param       mixed $value Specifies the value of the input element.
     *
     * @param       string $name The name of the input field.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($name, $value = null, $parameters = []) {

        $parameters['name'] = $name;

        parent::__construct('textarea', $value, $parameters);

    }

}
