<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML figcaption class.
 *
 * @detail      Displays an HTML &lt;figcaption&gt; element.
 *
 * @since       1.1
 */
class Figcaption extends Block {

    static private $count = 0;
    
    /**
     * @detail      The HTML figcaption constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        $content = 'Fig' . ++Figcaption::$count . '. - ' . $content;
        
        parent::__construct('figcaption', $content, $parameters);

    }

}
