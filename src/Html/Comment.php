<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML comment class.
 *
 * @detail      Displays an HTML &lt;!--...--&gt; element.
 *
 * @since       1.1
 */
class Comment extends Element {

    private $content;
    
    /**
     * @detail      The HTML comment constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content) {

        parent::__construct(null, null);
        
        $this->content = $content;

    }
    
    public function renderObject(){
        
        return '<!--' . $this->type . $this->content . '-->';
        
    }

}
