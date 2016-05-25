<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Menu widget.
 *
 * @since           1.1
 */
class ProgressBar extends Widget {

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
     * @detail      Sets or gets the progress bar's value The value should be set between min(default value: 0) and
     *              max(default value: 100).
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
     * @detail      Sets or gets the progress bar's max value.
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
     * @detail      Sets or gets the progress bar's min value.
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
     * @detail      Sets or gets the orientation.
     *
     *              Possible Values:
     *              * 'vertical'
     *              * 'horizontal'
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function orientation($value) {

        return $this->set('orientation', $value, 'string');

    }

    /**
     * @detail      Sets or gets the visibility of the progress bar's percentage's text.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function showText($value) {

        return $this->set('showText', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the jqxProgressBar's layout.
     *
     *              Possible Values:
     *              * 'normal'
     *              * 'reverse'-the slider is filled from right-to-left(horizontal progressbar) and from
     *              top-to-bottom(vertical progressbar)
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function layout($value) {

        return $this->set('disabled', $value, 'string');

    }

    /**
     * @detail      Determines the duration of the progressbar's animation.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function animationDuration($value) {

        return $this->set('disabled', $value, 'int');

    }

    /**
     * @detail      Determines the duration of the progressbar's animation.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function onValuechanged($code) {

        return $this->event('valuechanged', $code);

    }

    /**
     * @detail      This event is triggered when the user enters an invalid value( value which is not Number or is out of
     *              the min - max range. )
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function onInvalidvalue($code) {

        return $this->event('invalidvalue', $code);

    }

    /**
     * @detail      This event is triggered when the value is equal to the max. value.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function onComplete($code) {

        return $this->event('complete', $code);

    }

    /**
     * @detail      Sets the progress bar's value.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function actualValue() {

        return $this->method('actualValue');

    }

    /**
     * @detail      Sets or gets the value.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function val($value = null) {

        return $this->method('value', $value);

    }

}
