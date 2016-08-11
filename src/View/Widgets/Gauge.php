<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Gauge widget class
 *
 * @since           1.1
 */
class Gauge extends Widget {

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
     * @detail      Sets or gets gauge's radius. This property accepts size in pixels and percentage.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function radius($value) {

        return $this->set('radius', $value, 'int');

    }

    /**
     * @detail      Sets or gets gauge's startAngle. This property specifies the beggining of the gauge's scale
     *              and is measured in degrees.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function startAngle($value) {

        return $this->set('startAngle', $value, 'int');

    }

    /**
     * @detail      Sets or gets gauge's endAngle. This property specifies the end of the gauge's scale and
     *              is measured in degrees.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function endAngle($value) {

        return $this->set('endAngle', $value, 'int');

    }

    /**
     * @detail      Sets or gets gauge's value.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function value($value = null) {

        return $this->set('value', $value, 'int');

    }

    /**
     * @detail      Sets or gets gauge's minimum value.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function min($value) {

        return $this->set('min', $value, 'int');

    }

    /**
     * @detail      Sets or gets jqxGauge's max value.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function max($value) {

        return $this->set('max', $value, 'int');

    }

    /**
     * @detail      Sets the gauge's color pallete. jqxGauge suppports 11 color schemes from 'scheme01' to 'scheme11'.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function colorScheme($value) {

        return $this->set('colorScheme', $value, 'string');

    }

    /**
     * @detail      Sets and gets the ticks position. This property can be specified using percents
     *              (between '0%' and '100%') or using pixels. If the ticksRadius is '0%' this will
     *              position the ticks in the outer border of the gauge and if it's '100%' ticks will
     *              be positioned near the center.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function ticksDistance($value) {

        return $this->set('ticksDistance', $value, 'int');

    }

    /**
     * @detail      Sets or gets jqxGauge's animation duration [ms].
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function animationDuration($value) {

        return $this->set('animationDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets jqxGauge's animation easing.
     *
     *              Possible easings are: 'linear', 'easeOutBack', 'easeInQuad', 'easeInOutCirc', 'easeInOutSine',
     *              'easeOutCubic'.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function easing($value) {

        return $this->set('easing', $value, 'string');

    }

    /**
     * @detail      This property indicates whether the gauge's ranges will be visible.
     *
     * @return      Hazaar\\jqWidgets\\Gauge
     */
    public function showRanges($value) {

        return $this->set('showRanges', $value, 'bool');

    }

    /**
     * @detail      This property is array of objects. Each object is different range. The range is colored area with
     *              specified size.
     *
     *              Possible Values:
     *              * 'startValue'-the value from which the range will start
     *              * 'endValue'-the value where the current range will end
     *              * 'startWidth'-the width of the range in it's start
     *              * 'endWidth'-the end width of the range
     *              * 'startDistance [optional]'-this property is measured in pixels or percentage. It indicates the
     *              distance from the gauge's outer boundary to the start of the range
     *              * 'endDistance [optional]'-this property is measured in pixels or percentage. It indicates the
     *              distance from the gauge's outer boundary to the end of the range
     *              * 'style'-this property is object containing style information for the range. It accepts properties
     *              like 'fill', 'stroke', etc.

     *
     * @since       1.1
     *
     * @param       array $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function ranges($value) {

        return $this->set('labels', $value);

    }

    /**
     * @detail      Sets or gets the gauge's style.
     *
     * @since       1.1
     *
     * @param       object $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function style($value) {

        return $this->set('labels', $value);

    }

    /**
     * @detail      Sets or gets the gauge's properties for it's minor ticks.
     *
     *              Possible Values:
     *              * 'size'-specifies the length of the tick. This property can be set in pixels or in percentag
     *              * 'interval'-specifies the ticks frequency. With interval equals to 5 each fifth value of the gauge
     *              will have a minor tick
     *              * 'visible'-indicates if the minor ticks will be visible
     *              * 'style'-sets ticks style (color and thickness)
     *
     * @since       1.1
     *
     * @param       object $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function ticksMinor($value) {

        return $this->set('labels', $value);

    }

    /**
     * @detail      Sets or gets the gauge's properties for it's major ticks.
     *
     *              Possible Values:
     *              * 'size'-specifies the length of the tick. This property is measured in pixels or percentage
     *              * 'interval'-specifies the ticks frequency. With interval equals to 5 each fifth value of the gauge
     *              will have a major tick
     *              * 'visible'-indicates if the major ticks will be visible
     *              * 'style'-sets ticks style (color and thickness)
     *
     * @since       1.1
     *
     * @param       object $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function ticksMajor($value) {

        return $this->set('labels', $value);

    }

    /**
     * @detail      Sets or gets the gauge's properties for it's border.
     *
     *              Possible Values:
     *              * 'size'-specifies the size of the border. Border's size can be set in percentage or in pixels
     *              * 'visible'-indicates if the border will be visible
     *              * 'style'-sets border style (color and thickness)
     *              * 'showGradient' - whether a gradient background will be used
     *
     * @since       1.1
     *
     * @param       object $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function border($value) {

        return $this->set('labels', $value);

    }

    /**
     * @detail      Sets or gets the gauge's properties for it's caption.
     *
     *              Possible Values:
     *              * 'value' - specifies the text
     *              * 'position' - specifies the caption position. There four different positions - top, bottom, left and
     *              right. You can customize the position using the offset property described bellow
     *              * 'offset' - array with two number elements. The first one indicates the left offset and the second
     *              one the top offset
     *              * 'visible' - indicates whether the caption will be visible
     *
     * @since       1.1
     *
     * @param       object $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function caption($value) {

        return $this->set('labels', $value);

    }

    /**
     * @detail      Sets or gets the gauge's properties for it's cap.
     *
     *              Possible Values:
     *              * 'size' - specifies the gauge's size. This property can be set as percentage or in pixels
     *              * 'visible' - indicates whether the cap will be visible
     *              * 'style' - specifies the gauge's cap styles. Here you can set it's fill or stroke colors
     *
     * @since       1.1
     *
     * @param       object $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function cap($value) {

        return $this->set('labels', $value);

    }

    /**
     * @detail      Sets or gets the gauge's properties for it's pointer.
     *
     *              Possible Values:
     *              * 'pointerType' - specifies the pointer type. Possible values for this property are - 'default' and
     *              'rectangle'. If it's value is 'default' the pointer will be arrow otherwise it'll be rectangle
     *              * 'style' - specifies the gauge's pointer style. Here you can set it's fill or stroke color
     *              * 'width' - specifies pointer's width. This property can be set in percents ('0%' - '100%') or in
     *              pixels
     *              * 'length' - specifies pointer's length. This property can be set in percents ('0%' - '100%') or in
     *              pixels
     *              * 'visible' - indicates whether the pointer will be visible
     *
     * @since       1.1
     *
     * @param       object $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function pointer($value) {

        return $this->set('labels', $value);

    }

    /**
     * @detail      Sets or gets the gauge's properties for it's labels.
     *
     *              Possible Values:
     *              * 'distance' - specifies the labels distance from the gauge's center. This value could be set in
     *              percents ('0%' - '100%') or using pixels. This property is with lower priority than the position
     *              property
     *              * 'position' - specifies the gauge's labels position. Possible values for this property are 'inside',
     *              'outside' and 'none' (if you want to use the distance property). If it's value is inside the labels
     *              are going to be shown inside the scale otherwise they will be shown outside. This property is with
     *              higher priority than the distance property
     *              * 'interval' - specifies labels's frequency
     *              * 'offset' - specifies labels's offset. This property is array with two elements. The first one is
     *              the left offset and the second one is the top offset
     *              * 'style' - specifies the gauge's pointer style. Here you can set it's fill or stroke color
     *              * 'formatValue' - callback used for formatting the label. This function accepts a single parameter
     *              which the user can format and return to the labels renderer
     *              * 'visible' - indicates whether the labels will be visible
     *
     * @since       1.1
     *
     * @param       object $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function labels($value) {

        return $this->set('labels', $value);

    }

    /**
     * @detail      The event is is triggered when the gauge's value is changing.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function onValueChanging($code) {

        return $this->event($code);

    }

    /**
     * @detail      The event is is triggered when the gauge's value is changed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function onValueChanged($code) {

        return $this->event($code);

    }

}
