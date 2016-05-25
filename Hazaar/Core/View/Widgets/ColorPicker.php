<?php

namespace Hazaar\View\Widgets;

/**
 * @detail      Toggle ColorPicker widget.
 *
 * @since       1.1
 */
class ColorPicker extends Widget {

    /**
     * @detail      Initialise a ColorPicker widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       string $label The label to display on the button.
     */
    function __construct($name) {

        parent::__construct('div', $name);

    }

    /**
     * @detail      Enables or disables the color picker.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\ColorPicker
     */
    public function disabled($value) {

        return $this->set('disabled', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the color mode.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\ColorPicker
     */
    public function colorMode($value) {

        return $this->set('colorMode', $value, 'string');

    }

    /**
     * @detail      Sets or gets the showTransparent property.
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widgets\\ColorPicker
     */
    public function showTransparent($value) {

        return $this->set('showTransparent', $value, 'bool');

    }

    /**
     * @detail      This event is triggered when a new color is picked.
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\ColorPicker
     */
    public function onColorchange($code) {

        return $this->event('colorchange', $code);

    }

    /**
     * @detail      Sets a color.
     *
     * @param       string $value The color value to set
     *
     * @return      string
     */
    public function setColor($value) {

        return $this->method('setColor', $value);

    }

    /**
     * @detail      Gets a color.
     *
     * @return      string
     */
    public function getColor() {

        return $this->method('getColor');

    }

}
