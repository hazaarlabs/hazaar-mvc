<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML video class.
 *
 * @detail      Displays an HTML &lt;video&gt; element.
 *
 * @since       1.1
 */
class Audio extends Block {

    /**
     * @detail      The HTML video constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($autoplay = false, $controls = true, $parameters = []) {

        if($autoplay)
            $parameters[] = 'autoplay';

        if($controls)
            $parameters[] = 'controls';

        parent::__construct('audio', null, $parameters);

    }

    public function source($source, $type = null, $params = []) {

        if(is_array($source)) {

            foreach($source as $src)
                $this->source($src);

        } elseif(!$source instanceof Source) {

            $source = new Source($source, $type, 'audio', $params);

        }

        return $this->add($source);

    }

}
