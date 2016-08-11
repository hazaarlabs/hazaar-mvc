<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Basic button widget.
 *
 * @since           1.1
 */
class Button extends Widget {

    /**
     * @detail      Initialise a button widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       string $label The label to display on the button.
     */
    function __construct($name, $label = 'Button', $button_style = null, $params = array()) {
        
        if($button_style){
            
            $params['class'] = (array_key_exists('class', $params)?$params['class'] . ' ':'') . 'jqx-' . $button_style;;
            
        }

        parent::__construct('button', $name, $params, false, $label);

    }

    /**
     * @detail      Set the button label
     *
     * @return      Hazaar\\jqWidgets\\Button
     */
    public function label($value) {

        return $this->attr('value', $value);

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
