<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML link class.
 *
 * @detail      Displays an HTML &lt;link&gt; element.
 *
 * @since       1.1
 */
class Link extends Inline {

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
    function __construct($href, $rel = null, $type = null, $parameters = array()) {

        $parameters['href'] = $href;

        if(!$rel || !$type) {

            $info = pathinfo($href);

            if(array_key_exists('extension', $info)) {

                if(!$rel) {

                    if(strtolower($info['extension']) == 'css') {

                        $rel = 'stylesheet';

                    }

                }

                if(!$type) {

                    if(strtolower($info['extension']) == 'css') {

                        $type = 'text/css';

                    }

                }

            }

        }

        $parameters['href'] = $href;

        if($rel)
            $parameters['rel'] = $rel;

        if($type)
            $parameters['type'] = $type;

        parent::__construct('link', $parameters);

    }

}
