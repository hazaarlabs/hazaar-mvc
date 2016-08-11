<?php

namespace Hazaar\View\Widgets\Button;

/**
 * @detail          Toggle button widget.
 *
 * @since           1.0.0
 */
class Link extends \Hazaar\View\Widgets\Widget {

    function __construct($name, $link, $label = 'Button', $params = array()) {

        $params['href'] = $link;

        parent::__construct('a', $name, $params, false, $label);

    }

    function name() {

        return 'LinkButton';

    }

    /**
     * @detail      Enables or disables the rounded corners functionality. This property setting has effect in
     *              browsers which support CSS border-radius.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function roundedCorners($value) {

        return $this->set('roundedCorners', (string)$value);

    }

    /**
     * @detail      Enables or disables the button.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function disabled($value) {

        return $this->set('disabled', (bool)$value);

    }

}
