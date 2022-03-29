<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML definition list class.
 *
 * @detail      Displays an HTML &lt;sl&gt; element.
 *
 * @since       1.1
 */
class Dl extends Block {

    /**
     * @detail      The HTML definition list constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the div.
     */
    function __construct($items = null, $params = []) {

        parent::__construct('dl', null, $params);

        if(is_array($items))
            foreach($items as $item)
                $this->dd($item);

    }

    public function dt($content) {

        if(!$content instanceof Dt)
            $content = new Dt($content);

        $this->add($content);

        return $this;

    }

    public function dd($content) {

        if(!$content instanceof Dd)
            $content = new Dd($content);

        $this->add($content);

        return $this;

    }

}

