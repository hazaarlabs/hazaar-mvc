<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Basic button widget.
 *
 * @since           1.1
 */
class Panel extends Widget {

    /**
     * @detail      Initialise a button widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       string $label The label to display on the button.
     */
    function __construct($name, $content = null) {

        parent::__construct('div', $name, array('type' => 'button'), false, $content);

    }

    /**
     * @detail      Sets or gets the sizing mode. In the 'fixed' mode, the panel displays scrollbars, if its content
     *              requires it. In the wrap mode, the scrollbars are not displayed and the panel automatically changes
     *              its size.
     * 
     *              Possible Values:
     *              * 'fixed'
     *              * 'wrap'
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Panel
     */
    public function sizeMode($value) {

        return $this->set('sizeMode', $value, 'string');

    }

    /**
     * @detail      Automatically updates the panel, if its children size is changed.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Panel
     */
    public function autoUpdate($value) {

        return $this->set('autoUpdate', $value, 'bool');

    }

    /**
     * @detail      Sets or gets whether the panel is disabled.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Panel
     */
    public function disabled($value) {

        return $this->set('disabled', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the scrollbar's size.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Panel
     */
    public function scrollBarSize($value) {

        return $this->set('scrollBarSize', $value, 'int');

    }

    /**
     * @detail      Occurs when the layout is performed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Panel
     */

    public function onLayout($code) {

        return $this->event('layout', $code);

    }

    /**
     * @detail      Appends an element to the panel's content.
     *
     * @since       1.1
     *
     * @param       string $value The element to append
     *
     * @return      string
     */
    public function append($content) {

        return $this->method('append', $content);

    }

    /**
     * @detail      Prepends an element to the panel's content.
     *
     * @since       1.1
     *
     * @param       string $value The element to prepend
     *
     * @return      string
     */
    public function prepend($content) {

        return $this->method('prepend', $content);

    }

    /**
     * @detail      Remove an element from the panel's content.
     *
     * @since       1.1
     *
     * @param       string $value The element to remove
     *
     * @return      string
     */
    public function remove($content) {

        return $this->method('remove', $content);

    }

    /**
     * @detail      Clears the panel's content.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      string
     */
    public function clearcontent() {

        return $this->method('clearcontent');

    }

    /**
     * @detail      Scroll to specific position.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      string
     */
    public function scrollTo($x = 0, $y = 0) {

        return $this->method('scrollTo', $x, $y);

    }

    /**
     * @detail      Get the scrollable height. Returns a Number.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getScrollHeight() {

        return $this->method('getScrollHeight');

    }

    /**
     * @detail      Get the vertical scrollbar's position. Returns a Number.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getVScrollPosition() {

        return $this->method('getVScrollPosition');

    }

    /**
     * @detail      Get the scrollable width. Returns a Number.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getScrollWidth() {

        return $this->method('getScrollWidth');

    }

    /**
     * @detail      Get the horizontal scrollbar's position. Returns a Number.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getHScrollPosition() {

        return $this->method('getHScrollPosition');

    }

}
