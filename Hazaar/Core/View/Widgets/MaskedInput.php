<?php

namespace Hazaar\View\Widgets;

/**
 * @brief           Basic button widget.
 *
 * @since           1.1
 */
class MaskedInput extends Widget {

    /**
     * @brief       Initialise a MaskedInput widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       string $value The initial value of the input.
     */
    function __construct($name, $value = null) {

        parent::__construct('input', $name, array(
            'type' => 'text',
            'value' => $value,
            'name' => $name
        ));

    }

    /**
     * @brief       Sets or gets the masked input's value.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function value($value) {

        return $this->set('value', $value, 'string');

    }

    /**
     * @brief       Sets or gets the masked input's mask.
     *
     * @detail      eg. "(##)####-####" would be nice for a phone number.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function mask($value) {

        return $this->set('mask', $value, 'string');

    }

    /**
     * @brief       Sets or gets the text alignment.
     *
     * @detail      Possible Values:
     *              * 'right'
     *              * 'left'
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function textAlign($value) {

        return $this->set('textAlign', $value, 'string');

    }

    /**
     * @brief       Sets or gets the readOnly state of the input.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function readOnly($value) {

        return $this->set('readOnly', $value, 'bool');

    }

    /**
     * @brief       Sets or gets the prompt char displayed when an editable char is empty.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function promptChar($value) {

        return $this->set('promptChar', $value, 'string');

    }

    /**
     * @brief       This event is triggered when the value is changed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function onValuechanged($code) {

        return $this->event('valuechanged', $code);

    }

    /**
     * @brief       This event is triggered when the value is changed and the control's focus is lost.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @brief       This event is triggered when the text is changed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function onTextchanged($code) {

        return $this->event('textchanged', $code);

    }

    /**
     * @brief       Sets the editable input value without mask characters.
     *
     * @detail      For example: If your mask string is set to '(###)###' and you invoke the maskedValue method passing
     *              '4444' as parameter, the jqxMaskedInput widget should display '(444)___'.
     *
     * @since       1.1
     *
     * @param       string $value The masked value to set
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function maskedValue($value) {

        return $this->method('maskedValue', (string)$value);

    }

    /**
     * @brief       Sets the editable input value without mask characters.
     *
     * @detail      For example: If your mask string is set to '(###)###' and you invoke the inputValue method passing
     *              '4444' as parameter, the jqxMaskedInput widget should display '(444)4__'.
     *
     * @since       1.1
     *
     * @param       string $value The input value to set
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function inputValue($value) {

        return $this->method('inputValue', (string)$value);

    }

    /**
     * @brief       Sets or gets the value.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function val() {

        return $this->method('val');

    }

    /**
     * @brief       Clears the value.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function clear() {

        return $this->method('clear');

    }

}
