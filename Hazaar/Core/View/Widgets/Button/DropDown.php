<?php

namespace Hazaar\View\Widgets\Button;

/**
 * @detail      Toggle button widget.
 *
 * @since       1.0.0
 */
class DropDown extends \Hazaar\View\Widgets\Widget {

    /**
     * @detail      Initialise a radiobutton widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       mixed $content The content you want to appear when the dropdown is clicked.
     */
    function __construct($name, $content, $params = array()) {

        parent::__construct('div', $name, $params, false, $content);

    }

    protected function name() {

        return "DropDownButton";

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

    /**
     * @detail      Execute the toggle method on a widget
     */
    public function toggle() {

        return $this->method('toggle');

    }

}
