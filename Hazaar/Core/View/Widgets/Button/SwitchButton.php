<?php

namespace Hazaar\View\Widgets\Button;

/**
 * @detail          Toggle button widget.
 *
 * @since           1.0.0
 */
class SwitchButton extends \Hazaar\View\Widgets\Widget {

    function __construct($name, $params = array()) {

        parent::__construct('div', $name, $params);

    }

    /**
     * @detail      Gets whether the switch button is disabled.
     *
     * @return      Hazaar\\jqWidgets\\Button\\SwitchButton
     */
    public function disabled($value) {

        return $this->set('disabled', $value, 'bool');

    }

    /**
     * @detail      Sets the orientation.
     *
     * @return      Hazaar\\jqWidgets\\Button\\SwitchButton
     */
    public function orientation($value) {

        return $this->set('orientation', $value, 'string');

    }

    /**
     * @detail      Sets the string displayed when the button is checked.
     *
     * @return      Hazaar\\jqWidgets\\Button\\SwitchButton
     */
    public function onLabel($value) {

        return $this->set('onLabel', $value, 'string');

    }

    /**
     * @detail      Sets the string displayed when the button is unchecked.
     *
     * @return      Hazaar\\jqWidgets\\Button\\SwitchButton
     */
    public function offLabel($value) {

        return $this->set('offLabel', $value, 'string');

    }
    
    /**
     * @detail      Sets the size of the thumb in percentages.
     *
     * @return      Hazaar\\jqWidgets\\Button\\SwitchButton
     */
    public function thumbSize($value) {

        return $this->set('thumbSize', $value, 'string');

    }
    
     /**
     * @detail      Gets or sets the ckeck state. Possible Values: true, false.
     *
     * @return      Hazaar\\jqWidgets\\Button\\SwitchButton
     */
    public function checked($value) {

        return $this->set('checked', $value, 'bool');

    }
    
    /**
     * @detail      This event is triggered when a switch is unchecked.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Group
     */
    public function onChecked($code) {

        return $this->event('checked', $code);

    }
    
    /**
     * @detail      This event is triggered when a switch is checked.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Group
     */
    public function onUnchecked($code) {

        return $this->event('unchecked', $code);

    }
    
    /**
     * @detail      This event is triggered when a switch state is changed.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Group
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }
    
    /**
     * @detail      Execute the check method on a widget
     */
    public function check() {

        return $this->method('check');

    }
    
    /**
     * @detail      Execute the uncheck method on a widget
     */
    public function uncheck() {

        return $this->method('uncheck');

    }
    
    /**
     * @detail      Execute the toggle method on a widget
     */
    public function toggle() {

        return $this->method('toggle');

    }
    
    /**
     * @detail      Execute the disable method on a widget
     */
    public function disable() {

        return $this->method('disable');

    }
    
    /**
     * @detail      Execute the enable method on a widget
     */
    public function enable() {

        return $this->method('enable');

    }

}
