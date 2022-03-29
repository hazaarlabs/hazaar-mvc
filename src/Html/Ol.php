<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML ordered list class.
 *
 * @detail      Displays an HTML &lt;ol&gt; element.
 *
 * @since       1.2
 */
class Ol extends Block {

    /**
     * @detail      The HTML ordered list constructor.
     *
     * @since       1.2
     *
     * @param       mixed $items The list items to add to the ordered list element.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($items = null, $parameters = []) {

        parent::__construct('ol', null, $parameters);

        if($items)
            $this->add($items);

    }

    /**
     * @detail      Add an element to the list.  Parameters can be text, an array of items, or a Hazaar\Li object or
     *              array of objects.
     *
     * @since       1.2
     *
     */
    public function add() {

        foreach(func_get_args() as $item) {

            if(is_array($item)) {

                foreach($item as $i) {

                    $this->add($i);

                }

            } else {

                parent::add(new Li($item));

            }

        }

        return $this;

    }

}
