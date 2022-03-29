<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML figure class.
 *
 * @detail      Displays an HTML &lt;figure&gt; element.
 *
 * @since       1.1
 */
class Figure extends Block {

    /**
     * @detail      The HTML figure constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($image = null, $caption = null, $parameters = []) {

        if(!$image instanceof Img) {

            $image = new Img($image);

        }

        if($caption) {

            if(!$caption instanceof Figcaption)
                $caption = new Figcaption($caption);

            $content = array(
                $image,
                $caption
            );

        } else {

            $content = $image;

        }

        parent::__construct('figure', $content, $parameters);

    }

}
