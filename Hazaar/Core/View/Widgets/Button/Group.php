<?php

namespace Hazaar\View\Widgets\Button;

/**
 * @detail      Toggle button widget.
 *
 * @since       1.0.0
 */
class Group extends \Hazaar\View\Widgets\Widget {

    /**
     * @detail      Initialise a radiobutton widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       string $buttons An array of buttons where the key is the button id and value is the label.
     */
    function __construct($name, $buttons = array(), $params = array()) {

        $btnDivs = array();

        foreach($buttons as $button_id => $label) {

            $btnDivs[] = new \Hazaar\Html\Button($label, null, array('id' => $button_id));

        }

        parent::__construct('div', $name, $params, false, $btnDivs);

    }

    protected function name() {

        return "ButtonGroup";

    }

    /**
     * @detail      Sets the jqxButtonGroup's mode. Possible values:'checkbox', 'radio', 'default'.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Button\\Group
     */
    public function mode($value) {

        return $this->set('mode', $value, 'string');

    }

    /**
     * @detail      Enables or disabled the highlight state.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Button\\Group
     */
    public function enableHover($value) {

        return $this->set('enableHover', $value, 'bool');

    }

    /**
     * @detail      This event is triggered when a button is selected - in checkboxes or radio buttons mode.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Group
     */
    public function onSelected($code) {

        return $this->event('selected', $code);

    }

    /**
     * @detail      This event is triggered when a button is unselected - in checkboxes or radio buttons mode.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Group
     */
    public function onUnselected($code) {

        return $this->event('unselected', $code);

    }

    /**
     * @detail      This event is triggered when a button is clicked.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Group
     */
    public function onButtonclick($code) {

        return $this->event('buttonclick', $code);

    }

    /**
     * @detail      Execute the setSelection method on a widget
     *
     * @param       bool $vaule The button id to set selected.
     */
    public function setSelection($value) {

        return $this->method('setSelection', $value);

    }

    /**
     * @detail      Execute the getSelection method on a widget
     */
    public function getSelection() {

        return $this->method('getSelection');

    }

    /**
     * @detail      Execute the enableAt method on a widget
     */
    public function enableAt($value) {

        return $this->method('enableAt', $value);

    }

    /**
     * @detail      Execute the disableAt method on a widget
     */
    public function disableAt($value) {

        return $this->method('disableAt', $value);

    }

    /**
     * @detail      Execute the enable method on a widget
     */
    public function enable() {

        return $this->method('enable');

    }

    /**
     * @detail      Execute the disable method on a widget
     */
    public function disable() {

        return $this->method('disable');

    }

}
