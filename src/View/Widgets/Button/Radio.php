<?php

namespace Hazaar\View\Widgets\Button;

/**
 * @detail      Repeat button widget.
 *
 * @since       1.0.0
 */
class Radio extends \Hazaar\View\Widgets\Widget {

    /**
     * @detail      Initialise a radiobutton widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       string $label The label to display on the button.
     */
    function __construct($name, $label = 'Radio Button', $params = array()) {

        parent::__construct('div', $name, $params, false, $label);

    }

    protected function name() {

        return "RadioButton";

    }

    /**
     * @detail      Gets or sets the delay of the fade animation when the CheckBox is going to be opened.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function animationShowDelay($value) {

        return $this->set('animationShowDelay', $value, 'int');

    }

    /**
     * @detail      Gets or sets the delay of the fade animation when the CheckBox is going to be closed.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function animationHideDelay($value) {

        return $this->set('animationHideDelay', $value, 'int');

    }

    /**
     * @detail      Gets or sets the checkbox's size.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function boxSize($value) {

        return $this->set('boxSize', $value);

    }

    /**
     * @detail      Gets or sets the ckeck state. Possible Values: true and false.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function checked($value = null) {

        return $this->set('checked', $value, 'bool');

    }

    /**
     * @detail      Gets or sets whether the clicks on the container are handled as clicks on the check box.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Button\\Radio
     */
    public function enableContainerClick($value) {

        return $this->set('enableContainerClick', $value, 'bool');

    }

    /**
     * @detail      Gets or sets whether the checkbox is locked. In this mode the user is not allowed to check/uncheck
     *              the checkbox.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function locked($value) {

        return $this->set('locked', $value, 'bool');

    }

    /**
     * @detail      Gets or sets the group name. When this property is set, the checkboxes in the same group behave as
     *              radio buttons.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Button\\Radio
     */
    public function groupName($value) {

        return $this->set('groupName', $value, 'string');

    }

    /**
     * @detail      This event is triggered when a RadioButton is checked.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Radio
     */
    public function onChecked($code) {

        return $this->event('check', $code);

    }

    /**
     * @detail      This event is triggered when a RadioButton is unchecked.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Radio
     */
    public function onUnchecked($code) {

        return $this->event('uncheck', $code);

    }

    /**
     * @detail      'Indeterminate' event is triggered when the RadioButton's checked property is going to be null.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Radio
     */
    public function onIndeterminate($code) {

        return $this->event('indeterminate', $code);

    }

    /**
     * @detail      This event is triggered when a RadioButton value is changed.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Button\\Radio
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

}
