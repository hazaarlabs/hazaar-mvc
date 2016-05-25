<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML abbr class.
 *
 * @detail      Displays an HTML &lt;abbr&gt; element.
 *
 * @since       1.1
 */
class Abbr extends Block {

    /**
     * @detail      The HTML abbr constructor.
     *
     * @since       1.1
     *
     * @param       string $title The full title of the abbreviation.
     *
     * @param       string $abbr The abbreviation.  If omitted, then the first abbreviation will be the uppercase string
     *              of the first character of each word in the title.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($title, $abbr = null, $parameters = array()) {

        if(!$abbr) {

            preg_match_all('/(\S)\S*/i', $title, $matches);

            $abbr = strtoupper(implode($matches[1]));

        }

        $parameters['title'] = $title;

        parent::__construct('abbr', $abbr, $parameters);

    }

}
