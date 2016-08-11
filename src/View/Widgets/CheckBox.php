<?php

namespace Hazaar\View\Widgets;

/**
 * @detail      Toggle button widget.
 *
 * @since       1.1
 */
class CheckBox extends Widget {

    /**
     * @detail      Initialise a checkbox widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       string $label The label to display on the button.
     */
    function __construct($name, $label = 'Checkbox', $params = array()) {

        parent::__construct('div', $name, $params);
        
        parent::add($label);

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
     * @detail      Gets or sets the ckeck state. Possible Values: true, false and null(when the hasThreeStates property
     *              value is true).
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function checked($value = null) {

        return $this->set('checked', $value, 'bool');

    }

    /**
     * @detail      Gets or sets whether the checkbox has 3 states - checked, unchecked and indeterminate.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function hasThreeStates($value) {

        return $this->set('hasThreeStates', $value, 'bool');

    }

    /**
     * @detail      Gets or sets whether the clicks on the container are handled as clicks on the check box.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
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
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function groupName($value) {

        return $this->set('groupName', $value, 'string');

    }

    /**
     * @detail      This event is triggered when a checkbox is checked.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function onChecked($code) {

        return $this->event('checked', $code);

    }

    /**
     * @detail      This event is triggered when a checkbox is unchecked.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function onUnchecked($code) {

        return $this->event('unchecked', $code);

    }

    /**
     * @detail      'Indeterminate' event is triggered when the checkbox's ckecked property is going to be null.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function onIndeterminate($code) {

        return $this->event('indeterminate', $code);

    }

    /**
     * @detail      This event is triggered when a checkbox value is changed.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Checkbox
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

}
