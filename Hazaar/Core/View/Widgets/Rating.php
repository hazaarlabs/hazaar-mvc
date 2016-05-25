<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Number input widget.
 *
 * @since           1.1
 */
class Rating extends Widget {

    /**
     * @detail      Initialise a Rating widget
     *
     * @param       string $name The ID of the button element to create.
     *
     * @param       int $value The initial value of the widget.
     */
    function __construct($name, $value) {

        parent::__construct('div', $name);

        if($value !== null)
            $this->value($value);

    }

    /**
     * @detail      Sets or gets images count.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Rating
     */
    public function count($value = null) {

        return $this->set('count', $value, 'int');

    }

    /**
     * @detail      Gets or sets current rating.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Rating
     */
    public function value($value = null) {

        return $this->set('value', $value, 'int');

    }

    /**
     * @detail      Gets or sets vote precision.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Rating
     */
    public function precision($value = null) {

        return $this->set('precision', $value, 'int');

    }

    /**
     * @detail      Gets or sets whether the user can vote single or multiple times.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Rating
     */
    public function singleVote($value = null) {

        return $this->set('singleVote', $value, 'bool');

    }

    /**
     * @detail      Gets or sets rating item's height.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Rating
     */
    public function itemHeight($value = null) {

        return $this->set('itemHeight', $value, 'int');

    }

    /**
     * @detail      Gets or sets rating item's width.
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Rating
     */
    public function itemWidth($value = null) {

        return $this->set('itemWidth', $value, 'int');

    }

    /**
     * @detail      The change event is triggered when the rating is changed.
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @detail      Sets the value.
     *
     * @param       string $value The value to set
     */
    public function setValue($value) {

        return $this->method('setValue', $value);

    }

    /**
     * @detail      Getting current rating value.
     *
     * @param       string $value The value to set
     */
    public function getValue($value) {

        return $this->method('getValue', $value);

    }

    /**
     * @detail      Disabling the widget.
     *
     * @param       string $value The value to set
     */
    public function disable($value) {

        return $this->method('disable', $value);

    }

    /**
     * @detail      Enabling the widget.
     *
     * @param       string $value The value to set
     */
    public function enable($value) {

        return $this->method('enable', $value);

    }

}
