<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Slider widget.
 *
 * @since           1.1
 */
class Slider extends Widget {

    private $content;

    /**
     * @detail      Initialise a Slider widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       array $params Optional additional parameters
     */
    function __construct($name, $params = array()) {

        parent::__construct('div', $name, $params);

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       int $value The value to set.
     *
     * @return      string
     */
    public function max($value) {

        return $this->set('values', $value, 'int');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       int $value The value to set.
     *
     * @return      string
     */
    public function min($value) {

        return $this->set('values', $value, 'int');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       int $value The value to set.
     *
     * @return      string
     */
    public function step($value) {

        return $this->set('values', $value, 'int');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     *
     * @param       bool $value The value to set.
     * 
     * @return      string
     */
    public function showTicks($value) {

        return $this->set('values', $value, 'bool');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       string $value The value to set.
     *
     * @return      string
     */
    public function ticksPosition($value) {

        return $this->set('values', $value, 'string');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       int $value The value to set.
     *
     * @return      string
     */
    public function ticksFrequency($value) {

        return $this->set('values', $value, 'int');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       bool $value The value to set.
     *
     * @return      string
     */
    public function showButtons($value) {

        return $this->set('values', $value, 'bool');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       string $value The value to set.
     *
     * @return      string
     */
    public function buttonsPosition($value) {

        return $this->set('values', $value, 'string');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     *
     * @param       string $value The value to set.
     * 
     * @return      string
     */
    public function mode($value) {

        return $this->set('values', $value, 'string');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       string $value The value to set.
     *
     * @return      string
     */
    public function layout($value) {

        return $this->set('values', $value, 'string');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       bool $value The value to set.
     *
     * @return      string
     */
    public function showRange($value) {

        return $this->set('values', $value, 'bool');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       bool $value The value to set.
     *
     * @return      string
     */
    public function rangeSlider($value) {

        return $this->set('values', $value, 'bool');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       bool $value The value to set.
     *
     * @return      string
     */
    public function tooltip($value) {

        return $this->set('values', $value, 'bool');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       mixed $value The value to set.
     *
     * @return      string
     */
    public function value($value) {

        return $this->set('values', $value);

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       mixed $value The value to set.
     *
     * @return      string
     */
    public function values($value) {

        return $this->set('values', $value);

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      string
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      string
     */
    public function onSlide($code) {

        return $this->event('slide', $code);

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      string
     */
    public function onSlideStart($code) {

        return $this->event('slideStart', $code);

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     * 
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      string
     */
    public function onSlideEnd($code) {

        return $this->event('created', $code);

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     * 
     * @return      string
     */
    public function onCreated($code) {

        return $this->event('created', $code);

    }

    /**
     * @detail      Increases the jqxSlider's value with the value of the 'step' property.
     *
     * @since       1.1
     * 
     * @return      string
     */
    public function incrementValue() {

        return $this->method('incrementValue');

    }

    /**
     * @detail      Decreases the jqxSlider's value with the value of the 'step' property.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function decrementValue() {

        return $this->method('decrementValue');

    }

    /**
     * @detail      Sets the jqxSlider's value. When the slider is not in range slider mode, the required parameter for
     *              the value is a number which should be in the 'min' - 'max' range. Possible value types in range
     *              slider mode- array, object or two numbers.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      string
     */
    public function setValue($value) {

        return $this->method('setValue', $value);

    }

    /**
     * @detail      Gets the slider's value. The returned value is a Number or an Object. If the Slider is a range
     *              slider, the method returns an Object with the following fields: rangeStart - the range's start value
     *              and rangeEnd - the range's end value..
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getValue() {

        return $this->method('getValue');

    }

}
