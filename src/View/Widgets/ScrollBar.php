<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Menu widget.
 *
 * @since           1.1
 */
class ScrollBar extends Widget {

    private $content;

    /**
     * @detail      Initialise an Menu widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       array $params Optional additional parameters
     */
    function __construct($name, $params = array()) {

        parent::__construct('div', $name, $params);

    }

    /**
     * @detail      Sets or gets the scrollbar's orientation.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function vertical($value) {

        return $this->set('vertical', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the scrollbar's minimum value.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function min($value) {

        return $this->set('min', $value, 'int');

    }

    /**
     * @detail      Sets or gets the scrollbar's maximum value.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function max($value) {

        return $this->set('max', $value, 'int');

    }

    /**
     * @detail      Sets or gets the scrollbar's value.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function value($value) {

        return $this->set('value', $value, 'int');

    }

    /**
     * @detail      Sets or gets the scrollbar's step. The value is increased/decreased with this step when the user
     *              presses a scrollbar button.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function step($value) {

        return $this->set('step', $value, 'int');

    }

    /**
     * @detail      Sets or gets the scrollbar's largestep. The value is increased/decreased with this largestep when the
     *              ser presses the left mouse button in the area between a scrollbar button and thumb.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function largestep($value) {

        return $this->set('largestep', $value, 'int');

    }

    /**
     * @detail      Specifies the scrollbar thumb's minimum size.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function thumbMinSize($value) {

        return $this->set('thumbMinSize', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether the scrollbar displays the increase and decrease arrow buttons.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function showButtons($value) {

        return $this->set('showButtons', $value, 'bool');

    }

    /**
     * @detail      This event is triggered when the value is changed.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function onValuechanged($code) {

        return $this->event('valuechanged', $code);

    }

    /**
     * @detail      Sets the thumb's position
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      string
     */
    public function setPosition($position) {

        return $this->method('setPosition', $position);

    }

    /**
     * @detail      Returns true, if the user is scrolling. Otherwise, returns false.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      string
     */
    public function isScrolling() {

        return $this->method('isScrolling');

    }

}
