<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML meta class.
 *
 * @detail      Displays an HTML &lt;meta&gt; element.
 *
 * @since       1.1
 */
class Meta extends Inline {

    /**
     * @detail      The HTML meta constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content, $name = null, $parameters = array()) {

        $parameters['content'] = $content;

        if($name)
            $parameters['name'] = $name;

        parent::__construct('meta', $parameters);

    }
    
    public function http_equiv($content){
        
        return $this->setParam('http-equiv', $content);
        
    }

}
