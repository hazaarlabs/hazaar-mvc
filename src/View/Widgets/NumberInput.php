<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Number input widget.
 *
 * @since           1.1
 */
class NumberInput extends Widget {

    /**
     * @detail      Initialise a NumberInput widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       int $value The initial value of the widget.
     */
    function __construct($name, $value = null) {

        parent::__construct('div', $name);

        if($value)
            $this->decimal($value);

    }

    /**
     * @detail      Gets or sets the input's number.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function decimal($value = null) {

        return $this->set('decimal', $value, 'int');

    }

    /**
     * @detail      Gets or sets the input's minimum value.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function min($value = null) {

        return $this->set('min', $value, 'int');

    }

    /**
     * @detail      Gets or sets the input's maximum value.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function max($value = null) {

        return $this->set('max', $value, 'int');

    }

    /**
     * @detail      Sets the validation message of the jqxNumberInput. This message is displayed when the value is not in
     *              the min - max range.
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function validationMessage($value) {

        return $this->set('validationMessage', $value, 'string');

    }

    /**
     * @detail      Sets the input mode. Possible values: 'advanced' and 'simple'. In the advanced mode, the number input
     *              behavior resembles a masked input with numeric mask. In the simple mode, the widget works as a normal
     *              textbox, but restricts the user's input to numbers.
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function inputMode($value) {

        return $this->set('inputMode', $value, 'string');

    }

    /**
     * @detail      Sets the spin mode. Possible values: 'none', 'advanced' and 'simple'. In the advanced mode, the value
     *              is increased depending on the caret's position. The 'none' mode specifies that the spin behavior is
     *              disabled.
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function spinMode($value) {

        return $this->set('spinMode', $value, 'string');

    }

    /**
     * @detail      Shows or hides the spin buttons.
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function spinButtons($value) {

        return $this->set('spinButtons', $value, 'bool');

    }

    /**
     * @detail      Sets the width of the spin buttons.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function spinButtonsWidth($value) {

        return $this->set('spinButtonsWidth', $value, 'int');

    }

    /**
     * @detail      Sets the increase/decrease step.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function spinButtonsStep($value) {

        return $this->set('spinButtonsStep', $value, 'int');

    }

    /**
     * @detail      Sets the alignment.
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function textAlign($value) {

        return $this->set('textAlign', $value, 'string');

    }

    /**
     * @detail      Gets or Sets the readOnly state of the input.
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function readOnly($value) {

        return $this->set('readOnly', $value, 'bool');

    }

    /**
     * @detail      Sets the prompt char displayed when an editable char is empty. Possible Values: "_"; "?"; "#".
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function promptChar($value) {

        return $this->set('promptChar', $value, 'string');

    }

    /**
     * @detail      Indicates the number of decimal places to use in numeric values.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function decimalDigits($value) {

        return $this->set('decimalDigits', $value, 'int');

    }

    /**
     * @detail      Gets or sets the char to use as the decimal separator in numeric values.
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function decimalSeparator($value) {

        return $this->set('decimalSeparator', $value, 'string');

    }

    /**
     * @detail      Gets or sets the string that separates groups of digits to the left of the decimal in numeric values.
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function groupSeparator($value) {

        return $this->set('groupSeparator', $value, 'string');

    }

    /**
     * @detail      Gets or sets the number of digits in each group to the left of the decimal in numeric values.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function groupSize($value) {

        return $this->set('groupSize', $value, 'int');

    }

    /**
     * @detail      Gets or sets the string to use as currency or percentage symbol.
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function symbol($value) {

        return $this->set('symbol', $value, 'string');

    }

    /**
     * @detail      Gets or sets the position of the symbol in the input.
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function symbolPosition($value) {

        return $this->set('symbolPosition', $value, 'string');

    }

    /**
     * @detail      Gets or sets the digits in the input
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function digits($value) {

        return $this->set('digits', $value, 'int');

    }

    /**
     * @detail      Gets or sets the string to use as negative symbol.
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function negativeSymbol($value) {

        return $this->set('negativeSymbol', $value, 'string');

    }

    /**
     * @detail      This event is triggered after value is changed.
     *
     * @param       string $code The JavaScript code to execute when the event is triggered
     */
    public function onValuechanged($code) {

        return $this->event('valuechanged', $code);

    }

    /**
     * @detail      This event is triggered when the value is changed and the control's focus is lost.
     *
     * @param       string $code The JavaScript code to execute when the event is triggered
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @detail      This event is triggered when the user entered entered a text.
     *
     * @param       string $code The JavaScript code to execute when the event is triggered
     */
    public function onTextchanged($code) {

        return $this->event('textchanged', $code);

    }

    /**
     * @detail      Clears the value.
     */
    public function clearDecimal() {

        return $this->method('clearDecimal');

    }

    /**
     * @detail      Gets or sets the value including the formatting characters such as group and decimal separators.
     *
     * @param       string $value The value to set
     */
    public function inputValue($value = null) {

        return $this->method('inputValue', $value);

    }

    /**
     * @detail      Gets the value.
     */
    public function getDecimal() {

        return $this->method('getDecimal');

    }

    /**
     * @detail      Sets the value.
     *
     * @param       string $value The value to set
     */
    public function setDecimal($value) {

        return $this->method('setDecimal', $value);

    }

    /**
     * @detail      Gets or sets the value.
     *
     * @param       string $value The value to set
     */
    public function val($value = null) {

        return $this->method('val', $value);

    }

}
