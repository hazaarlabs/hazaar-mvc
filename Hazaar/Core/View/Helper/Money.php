<?php
/**
 * @file        Hazaar/View/Helper/Currency.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Currency output functionality
 *
 * @detail      This view helper provides some functionality for producing currency related output.
 *
 * @ingroup     view_helpers
 *
 * @since       1.0.0
 */
class Money extends \Hazaar\View\Helper {

    /**
     * @detail      Initialise the view helper
     */
    public function init($view, $args = array()) {

    }

    public function format($number, $currency = null) {

        return new \Hazaar\Money($number, $currency);

    }

    public function __default($number, $currency = null) {

        return $this->format($number, $currency);

    }

}
