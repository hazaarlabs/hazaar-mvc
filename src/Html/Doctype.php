<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML DOCTYPE class.
 *
 * @detail      Displays an HTML &lt;DOCTYPE&gt; element.
 *
 * @since       1.1
 */
class Doctype extends Block {

    /**
     * @detail      The HTML DOCTYPE constructor.
     *
     * @since       1.1
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($html = true, $level = 5, $strict = true, $parameters = array()) {

        $parameters[] = 'HTML';

        $dec = null;

        $source = null;

        //Use HTML
        if($html) {

            if($level < 5) {

                $dec = '-//W3C//DTD HTML 4.01' . ($strict ? null : ' Transitional') . '//EN';

                $source = 'http://www.w3.org/TR/html4/loose.dtd';

                $parameters[] = 'PUBLIC';

            }

            //Use XHTML
        } else {

            if($level > 1) {

                $dec = '-//W3C//DTD XHTML 1.1//EN';

                $source = 'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd';

            } else {

                $dec = '-//W3C//DTD XHTML 1.0 ' . ($strict ? 'Strict' : 'Transitional') . '//EN';

                $source = 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd';

            }

        }

        if($dec)
            $parameters[] = '"' . $dec . '"';

        if($source)
            $parameters[] = '"' . $source . '"';

        parent::__construct('!DOCTYPE', null, $parameters, false);

    }

}
