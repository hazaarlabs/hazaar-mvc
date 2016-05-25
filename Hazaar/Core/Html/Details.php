<?php

namespace Hazaar\Html;

/**
 * @brief       Block HTML display element
 *
 * @detail      Generic base class for an HTML block element.  This class will render any block element of the defined
 *              type along with any child elements that have been set as it's contents.
 *
 * @since       1.0.0
 */
class Details extends Block {

    private $summary;

    /**
     * @detail      The HTML block element constructor.  This allows a block element of any type to be constructed.
     *
     * @since       1.2
     *
     * @param       mixed $content Any content to add to the element.  Content can be a string, an integer, another HTML
     *              element, or an array of any depth containing a mix of strings and HTML elements.
     *
     * @param       mixed $summary A string or Summary object to add to the content as the summary.
     *
     * @param       array $parameters An array of parameters to apply to the block element.
     *
     */
    function __construct($content, $summary = null, $parameters = array()) {

        if($summary) {

            if(!$summary instanceof Summary)
                $summary = new Summary($summary);

            $this->summary = $summary;

        }

        parent::__construct('details', $content, $parameters);

    }

    /**
     * @detail      Add content items to the details block.  If any of the arguments is a Summary object then it will be
     *              kept aside and prepended to the content during render.
     *
     * @since       1.2
     *
     * @return      \Hazaar\Html\Details
     */
    public function add() {

        foreach(func_get_args() as $arg) {

            if($arg instanceof Summary) {

                $this->summary = $arg;

            } else {

                parent::add($arg);

            }

        }

        return $this;

    }

    /**
     * @detail      Render the object as a string.  This method overrides the standard block render method so that the
     * summary
     *              can be rendered at the beginning of the content section, and only once.
     *
     * @since       1.2
     *
     * @return      string
     */
    public function renderObject() {

        if($this->summary) {

            $this->prepend($this->summary);

        }

        return parent::renderObject();

    }

}
