<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML bi-directional override class.
 *
 * @detail      Displays an HTML &lt;bdo&gt; element.
 *
 * @since       1.1
 */
class Bdo extends Block {

    /**
     * @detail      The HTML bi-directional override constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $dir = null, $parameters = []) {

        if(!$dir)
            $dir = 'rtl';

        if($dir && in_array(strtolower($dir), ['rtl', 'ltr']))
            $parameters['dir'] = $dir;

        parent::__construct('bdo', $content, $parameters);

    }

}
