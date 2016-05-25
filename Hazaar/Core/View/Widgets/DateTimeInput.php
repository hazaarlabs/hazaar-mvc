<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Basic button widget.
 *
 * @since           1.1
 */
class DateTimeInput extends Widget {

    /**
     * @detail      Initialise a DateTimeInput widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       string $value The initial value of the input.
     */
    function __construct($name, $value = null, $params = array()) {

        parent::__construct('div', $name, $params);
        
        if($value) $this->value($value);

    }

    /**
     * @detail      Sets or gets the jqxDateTimeInput value.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function value($value) {

        return $this->set('value', $value, 'string');

    }

    /**
     * @detail      Sets or gets the jqxDateTimeInput's minumun date.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function minDate($date) {

        if(!$date instanceof \Hazaar\Date)
            $date = new \Hazaar\Date($date);

        $minDate = new JSONObject( array(
            $date->year(),
            $date->month(),
            $date->day()
        ));

        return $this->set('minDate', '!new Date(' . $minDate->renderProperties() . ')');

    }

    /**
     * @detail      Sets or gets the jqxDateTimeInput's maximum date.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function maxDate($date) {

        if(!$date instanceof \Hazaar\Date)
            $date = new \Hazaar\Date($date);

        $minDate = new JSONObject( array(
            $date->year(),
            $date->month(),
            $date->day()
        ));

        return $this->set('maxDate', '!new Date(' . $minDate->renderProperties() . ')');

    }

    /**
     * @detail      Sets or gets the date time input format of the date.
     *
     *              Possible Values:
     *              * 'd'-the day of the month
     *              * 'dd'-the day of the month
     *              * 'ddd'-the abbreviated name of the day of the week
     *              * 'dddd'-the full name of the day of the week
     *              * 'h'-the hour, using a 12-hour clock from 1 to 12
     *              * 'hh'-the hour, using a 12-hour clock from 01 to 12
     *              * 'H'-the hour, using a 24-hour clock from 0 to 23
     *              * 'HH'-the hour, using a 24-hour clock from 00 to 23
     *              * 'm'-the minute, from 0 through 59
     *              * 'mm'-the minutes,from 00 though59
     *              * 'M'-the month, from 1 through 12;
     *              * 'MM'-the month, from 01 through 12
     *              * 'MMM'-the abbreviated name of the month
     *              * 'MMMM'-the full name of the month
     *              * 's'-the second, from 0 through 59
     *              * 'ss'-the second, from 00 through 59
     *              * 't'-the first character of the AM/PM designator
     *              * 'tt'-the AM/PM designator
     *              * 'y'-the year, from 0 to 99
     *              * 'yy'-the year, from 00 to 99
     *              * 'yyy'-the year, with a minimum of three digits
     *              * 'yyyy'-the year as a four-digit number
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function formatString($value) {

        return $this->set('formatString', $value, 'string');

    }

    /**
     * @detail      Sets or gets the popup's z-index.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function popupZIndex($value) {

        return $this->set('popupZIndex', $value, 'int');

    }

    /**
     * @detail      Determines whether the "showCalendarButton" is visible.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function showCalendarButton($value) {

        return $this->set('showCalendarButton', $value, 'bool');

    }

    /**
     * @detail      Sets or gets which day to display in the first day column. By default the calendar displays 'Sunday'
     *              as first day.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function firstDayOfWeek($value) {

        return $this->set('firstDayOfWeek', $value, 'int');

    }

    /**
     * @detail      Sets or gets a value whether the week`s numbers are displayed.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function showWeekNumbers($value) {

        return $this->set('showWeekNumbers', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the position of the text.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function textAlign($value) {

        return $this->set('textAlign', $value, 'string');

    }

    /**
     * @detail      Set the readonly property .
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function readonly($value) {

        return $this->set('readonly', $value, 'bool');

    }

    /**
     * @detail      Sets or gets a value indicating whether the dropdown calendar's footer is displayed.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function showFooter($value) {

        return $this->set('showFooter', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the dropdown calendar's selection mode.
     *
     *              Possible Values:
     *              * 'none'
     *              * 'default'
     *              * 'range'
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function selectionMode($value) {

        return $this->set('selectionMode', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the 'Today' string displayed in the dropdown Calendar when the 'showFooter' property is
     *              true.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function todayString($value) {

        return $this->set('todayString', $value, 'string');

    }

    /**
     * @detail      Sets or gets the 'Clear' string displayed when the 'showFooter' property is true.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function clearString($value) {

        return $this->set('clearString', $value, 'string');

    }

    /**
     * @detail      Sets or gets the jqxDateTimeInput's culture. The culture settings are contained within a file with
     *              the language code appended to the name, e.g. jquery.glob.de-DE.js for German. To set the culture, you
     *              need to include the jquery.glob.de-DE.js and set the culture property to the culture's name, e.g.
     *              'de-DE'.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function culture($value) {

        return $this->set('culture', $value, 'string');

    }

    /**
     * @detail      Specifies the animation duration of the popup calendar when it is going to be displayed.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function showDelay($value) {

        return $this->set('showDelay', $value, 'int');

    }

    /**
     * @detail      Specifies the animation duration of the popup calendar when it is going to be hidden.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function hideDelay($value) {

        return $this->set('hideDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether or not the popup calendar must be closed after selection.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function closeCalendarAfterSelection($value) {

        return $this->set('closeCalendarAfterSelection', $value, 'bool');

    }

    /**
     * @detail      When this property is set to true, the popup calendar may open above the input, if there's not enough
     *              space below the DateTimeInput.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function enableBrowserBoundsDetection($value) {

        return $this->set('enableBrowserBoundsDetection', $value, 'bool');

    }

    /**
     * @detail      Sets the DropDown's alignment.
     *
     *              Possible Values:
     *              * 'left'
     *              * right'
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function dropDownHorizontalAlignment($value) {

        return $this->set('dropDownHorizontalAlignment', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the delay of the 'open' animation.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function openDelay($value) {

        return $this->set('openDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets the delay of the 'close' animation.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function closeDelay($value) {

        return $this->set('closeDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets the type of the animation.
     *
     *              Possible Values:
     *              * 'fade'
     *              * 'slide'
     *              * 'none'
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function animationType($value) {

        return $this->set('animationType', $value, 'string');

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function enableAbsoluteSelection($value) {

        return $this->set('enableAbsoluteSelection', $value, 'bool');

    }

    /**
     * @detail      This setting enables the user to select only one symbol at a time when typing into the text input
     *              field.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function onValuechanged($code) {

        return $this->event('valuechanged', $code);

    }

    /**
     * @detail      This event is triggered when the value is changed.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @detail      This event is triggered on blur when the value is changed .
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function onTextchanged($code) {

        return $this->event('textchanged', $code);

    }

    /**
     * @detail      This event is triggered when the popup calendar is opened.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function onOpen($code) {

        return $this->event('open', $code);

    }

    /**
     * @detail      This event is triggered when the popup calendar is closed.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function onClose($code) {

        return $this->event('close', $code);

    }

    /**
     * @detail      When the setMinDate method is called, the user sets the minimum date to which it is possible to
     *              navigate.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function setMinDate($date) {

        return $this->method('setMinDate', $date);

    }

    /**
     * @detail      When the getMinDate method is called, the user gets the minimum navigation date. The returned value
     *              is JavaScript Date Object.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function getMinDate() {

        return $this->method('getMinDate');

    }

    /**
     * @detail      When the setMaxDate method is called, the user sets the maximum date to which it is possible to
     *              navigate.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function setMaxDate($date) {

        return $this->method('setMaxDate', $date);

    }

    /**
     * @detail      When the setMaxDate method is called, the user gets the maximum navigation date. The returned value
     *              is JavaScript Date Object.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function getMaxDate() {

        return $this->method('getMaxDate');

    }

    /**
     * @detail      When the setDate method is called, the user sets the date. The required parameter is a JavaScript
     *              Date Object.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function setDate($date) {

        return $this->method('setDate', $date);

    }

    /**
     * @detail      When the getDate method is called, the user gets the current date. The returned value is JavaScript
     *              Date Object.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function getDate() {

        return $this->method('getDate');

    }

    /**
     * @detail      Returns the input field's text.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function getText() {

        return $this->method('getText');

    }

    /**
     * @detail      After calling this method, the popup calendar will be hidden.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function close() {

        return $this->method('close');

    }

    /**
     * @detail      After calling this method, the popup calendar will be displayed.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function open() {

        return $this->method('open');

    }

    /**
     * @detail      Sets the selection range when the selectionMode is set to 'range'. The required parameters are
     *              JavaScript Date Objects.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function setRange($start, $end) {

        return $this->method('setRange', $start, $end);

    }

    /**
     * @detail      Gets the selection range when the selectionMode is set to 'range'. The returned value is an Object
     *              with "from" and "to" fields. Each of the fields is a JavaScript Date Object.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function getRange() {

        return $this->method('getRange');

    }

    /**
     * @detail      Invoke the val method.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function val($value = null) {

        return $this->method('val', $value);

    }

}
