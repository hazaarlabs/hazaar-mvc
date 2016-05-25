<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML form class.
 *
 * @detail      Displays an HTML &lt;form&gt; element.
 *
 * @since       1.1
 */
class Form extends Block {

    /**
     * @detail      The HTML form constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       string $action Specifies where to send the form-data when a form is submitted
     * 
     * @param       string $method Specifies the HTTP method to use when sending form-data
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $action = null, $method = 'POST', $parameters = array()) {

        if($action)
            $parameters['action'] = $action;

        if($method)
            $parameters['method'] = $method;

        parent::__construct('form', $content, $parameters);

    }

}
