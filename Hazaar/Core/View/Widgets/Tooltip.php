<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Basic button widget.
 *
 * @since           1.1
 */
class Tooltip extends Widget {

    /**
     * @detail      Initialise a button widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       string $label The label to display on the button.
     */
    function __construct($object, $value = null, $params = array()) {

        if($object instanceof \Hazaar\Html\Element) {

            if(!($id = $object->get('id'))) {

                throw new \Exception('To set a tooltip on an object the object MUST have an ID attribute!');

            }

            $name = '#' . $id;

        } else {

            $name = '';

            if(!substr($object, 0, 1) == '#')
                $name = '#';

            $name .= (string)$object;

        }

        parent::__construct('div', $name, $params);

    }

    /**
     * @detail      Sets or gets the position of jqxTooltip.
     *
     *              Possible Values:
     *              * 'top' - the tooltip shows above the host element
     *              * 'bottom' - the tooltip shows below the host element
     *              * 'left' - the tooltip shows at the left of the host element
     *              * 'right' - the tooltip shows at the right of the host element
     *              * 'top-left' - the tooltip shows at the top-left side of the host element
     *              * 'bottom-left' - the tooltip shows at the bottom-left side of the host element
     *              * 'top-right' - the tooltip shows at the top-right side of the host element
     *              * 'bottom-right' - the tooltip shows at the bottom-right side of the host element
     *              * 'absolute' - the tooltip shows at an absolute position on screen, defined by the coordinate
     *              properties absolutePositionX and absolutePositionY
     *              * 'mouse' - the tooltip shows after a short period of time at the position of the mouse cursor
     *              * 'mouseenter' - the tooltip shows where the mouse cursor has entered the host element
     *              * 'default' - the tooltip shows at the bottom-right side of the host element but does not make use of
     *              the left and top properties

     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function position($value) {

        return $this->set('position', $value, 'string');

    }

    /**
     * @detail      Sets or gets whether jqxTooltip will be hidden if it leaves the browser bounds or will be offset so
     *              that it is always within the browser's bounds and visible.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function enableBrowserBoundsDetection($value) {

        return $this->set('enableBrowserBoundsDetection', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the content of jqxTooltip. It can be either plain text or HTML code.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function content($value) {

        return $this->set('content', $value, 'string');

    }

    /**
     * @detail      Sets or gets the horizontal offset of jqxTooltip based on the position property.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function left($value) {

        return $this->set('left', $value);

    }

    /**
     * @detail      Sets or gets the vertical offset of jqxTooltip based on the position property.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function top($value) {

        return $this->set('top', $value);

    }

    /**
     * @detail      Sets or gets the tooltip's horizontal position if the position property is set to 'absolute'.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function absolutePositionX($value) {

        return $this->set('absolutePositionX', $value);

    }

    /**
     * @detail      Sets or gets the tooltip's vertical position if the position property is set to 'absolute'.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function absolutePositionY($value) {

        return $this->set('absolutePositionY', $value);

    }

    /**
     * @detail      Sets or gets the way of triggering the tooltip.
     *
     *              Possible Values:
     *              * 'hover' - the tooltip shows immeadiately after hovering over the host element.
     *              * 'focus' - the tooltip shows after duration equal to the showDelay property after hovering over the
     *              host element
     *              * 'click' - the tooltip shows when the host element is clicked
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function trigger($value) {

        return $this->set('trigger', $value, 'string');

    }

    /**
     * @detail      Sets or gets the duration after which the tooltip will be shown if its trigger property is set to
     *              'focus'.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function showDelay($value) {

        return $this->set('showDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether the tooltip will automatically hide after duration equal to the autoHideDelay
     *              property.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function autoHide($value) {

        return $this->set('autoHide', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the duration after which the tooltip automatically hides (works only if the autoHide
     *              property is set to true).
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function autoHideDelay($value) {

        return $this->set('autoHideDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether the tooltip will close if it is clicked.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function closeOnClick($value) {

        return $this->set('closeOnClick', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the duration of the tooltip animation at show.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function animationShowDelay($value) {

        return $this->set('animationShowDelay', $value, 'int');
    }

    /**
     * @detail      Sets or gets the duration of the tooltip animation at hide.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function animationHideDelay($value) {

        return $this->set('animationHideDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether the tooltip's arrow will be shown.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function showArrow($value) {

        return $this->set('showArrow', $value, 'bool');

    }

    /**
     * @detail      This event is triggered when the tooltip is opened (shown).
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function onOpen($code) {

        return $this->event('close', $code);

    }

    /**
     * @detail      This event is triggered when the tooltip is closed (hidden).
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function onClose($code) {

        return $this->event('close', $code);

    }

    /**
     * @detail      Opens the tooltip.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function open() {

        return $this->method('open');

    }

    /**
     * @detail      Specifies a time before the tooltip closes. If it is not set, the tooltip closes immediately.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      string
     */
    public function close($value) {

        return $this->method('close', $value);

    }

}
