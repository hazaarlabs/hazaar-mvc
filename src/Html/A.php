<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML anchor class.
 *
 * @detail      Displays an HTML &lt;a&gt; element.
 *
 * @since       1.1
 */
class A extends Block {

    /**
     * @detail      The HTML anchor constructor.
     *
     * @since       1.1
     *
     * @param       string $href The URL to set as the HREF parameter. (required)
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($href = null, $content = NULL, $parameters = []) {

        if($href)
            $parameters['href'] = $href;

        parent::__construct('a', $content, $parameters);

    }

    public function renderObject() {

        if($this->count() == 0)
            $this->set($this->attr('href'));

        return parent::renderObject(); // TODO: Change the autogenerated stub

    }

}
