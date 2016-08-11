<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Calendar widget class
 *
 * @since           1.1
 */
class Calendar extends Widget {

    function __construct($name) {

        parent::__construct('div', $name);

    }

    private function getDateObject($value, $encap = false) {

        if(substr($value, 0, 1) != '!') {

            $date = new \Hazaar\Date($value);

            $year = $date->year();

            $month = $date->month() - 1;

            $day = $date->day();

            $script = array();

            $script[] = '!';

            if($encap)
                $script[] = '$.jqx._jqxDateTimeInput.getDateTime(';

            $script[] = "new Date($year, $month, $day)";

            if($encap)
                $script[] = ')';

            $value = implode($script);

        }

        return $value;

    }

    /**
     * @detail      Disables (true) or enables (false) the calendar. Can be set when initialising (first creating) the
     * calendar.
     *
     * @param       bool $value The initial state to set
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function disabled($value) {

        return $this->set('disabled', $value, 'bool');

    }

    /**
     * @detail      Represents the minimum navigation date.
     *
     * @param       \Hazaar\Date $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function minDate($value) {

        return $this->set('minDate', $this->getDateObject($value));

    }

    /**
     * @detail      Represents the maximum navigation date.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function maxDate($value) {

        return $this->set('maxDate', $this->getDateObject($value));

    }

    /**
     * @detail      Represents the calendar`s navigation step when the left or right navigation button is clicked.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function stepMonths($value) {

        return $this->set('stepMonths', $value, 'int');

    }

    /**
     * @detail      Sets the Calendar's value.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function value($value) {

        return $this->set('value', $this->getDateObject($value, true));

    }

    /**
     * @detail      Sets which day to display in the first day column. By default the calendar displays 'Sunday' as first
     * day.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function firstDayOfWeek($value) {

        return $this->set('firstDayOfWeek', $value, 'int');

    }

    /**
     * @detail      Sets a value whether the week`s numbers are displayed.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function showWeekNumbers($value) {

        return $this->set('showWeekNumbers', $value, 'bool');

    }

    /**
     * @detail      Sets a value whether the day names are displayed. By default, the day names are displayed.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function showDayNames($value) {

        return $this->set('showDayNames', $value, 'bool');

    }

    /**
     * @detail      Sets a value indicating whether weekend persists its view state.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function enableWeekend($value) {

        return $this->set('enableWeekend', $value, 'bool');

    }

    /**
     * @detail      Determines whether switching between month, year and decade views is enabled.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function enableViews($value) {

        return $this->set('enableViews', $value, 'bool');

    }

    /**
     * @detail      Sets a value indicating whether the other month days are enabled.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function enableOtherMonthDays($value) {

        return $this->set('enableOtherMonthDays', $value, 'bool');

    }

    /**
     * @detail      Sets a value whether the other month days are displayed.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function showOtherMonthDays($value) {

        return $this->set('showOtherMonthDays', $value, 'bool');

    }

    /**
     * @detail      Determines the animation delay between switching views.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function navigationDelay($value) {

        return $this->set('navigationDelay', $value, 'int');

    }

    /**
     * @detail      Sets the row header width.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function rowHeaderWidth($value) {

        return $this->set('rowHeaderWidth', $value, 'int');

    }

    /**
     * @detail      Sets the Calendar colomn header's height. In the column header are displayed the calendar day names.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function columnHeaderHeight($value) {

        return $this->set('columnHeaderHeight', $value, 'int');

    }

    /**
     * @detail      Sets the title height where the navigation arrows are displayed.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function titleHeight($value) {

        return $this->set('titleHeight', $value, 'int');

    }

    /**
     * @detail      Sets the name format of days of the week. Possible values: 'default', 'shortest', 'firstTwoLetters',
     * 'firstLetter', 'full'.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function dayNameFormat($value) {

        return $this->set('dayNameFormat', $value, 'string');

    }

    /**
     * @detail      Sets the title format for the title section. Possible values:
     *              * "d"-the day of the month;
     *              * "dd"-the day of the month;
     *              * "ddd"-the abbreviated name of the day of the week;
     *              * "dddd"- the full name of the day of the week;
     *              * "h"-the hour, using a 12-hour clock from 1 to 12;
     *              * "hh"-the hour, using a 12-hour clock from 01 to 12;
     *              * "H"-the hour, using a 24-hour clock from 0 to 23;
     *              * "HH"- the hour, using a 24-hour clock from 00 to 23;
     *              * "m"-the minute, from 0 through 59;
     *              * "mm"-the minutes,from 00 though59;
     *              * "M"- the month, from 1 through 12;
     *              * "MM"- the month, from 01 through 12;
     *              * "MMM"-the abbreviated name of the month;
     *              * "MMMM"-the full name of the month;
     *              * "s"-the second, from 0 through 59;
     *              * "ss"-the second, from 00 through 59;
     *              * "t"- the first character of the AM/PM designator;
     *              * "tt"-the AM/PM designator;
     *              * "y"- the year, from 0 to 99;
     *              * "yy"- the year, from 00 to 99;
     *              * "yyy"-the year, with a minimum of three digits;
     *              * "yyyy"-the year as a four-digit number;
     *              * "yyyyy"-the year as a four-digit number.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function titleFormat($value) {

        return $this->set('titleFormat', $value, 'string');

    }

    /**
     * @detail      Sets the calendar in read only state.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function readOnly($value) {

        return $this->set('readOnly', $value, 'bool');

    }

    /**
     * @detail      Sets a value indicating whether the calendar's footer is displayed.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function showFooter($value) {

        return $this->set('showFooter', $value, 'bool');

    }

    /**
     * @detail      Sets the selection mode. The possible values are: 'none', 'default' and 'range'.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function selectionMode($value) {

        return $this->set('selectionMode', $value, 'string');

    }

    /**
     * @detail      Sets the 'Today' string displayed when the 'showFooter' property is true.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function todayString($value) {

        return $this->set('todayString', $value, 'string');

    }

    /**
     * @detail      Sets the 'Clear' string displayed when the 'showFooter' property is true.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function clearString($value) {

        return $this->set('clearString', $value, 'string');

    }

    /**
     * @detail      Sets the jqxCalendar's culture. The culture settings are contained within a file with the language
     *              code appended to the name, e.g. jquery.glob.de-DE.js for German. To set the culture, you need to
     * include the
     *              jquery.glob.de-DE.js and set the culture property to the culture's name, e.g. 'de-DE'.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function culture($value) {

        return $this->set('culture', $value, 'string');

    }

    /**
     * @detail      Sets a value indicating whether the fast navigation is enabled.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function enableFastNavigation($value) {

        return $this->set('enableFastNavigation', $value, 'bool');

    }

    /**
     * @detail      Sets a value indicating whether the hover state is enabled. The hover state is activated when the
     *              mouse cursor is over a calendar cell. The hover state is automatically disabled when the calendar is
     * displayed in
     *              touch devices.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function enableHover($value) {

        return $this->set('enableHover', $value, 'bool');

    }

    /**
     * @detail      Sets a value indicating whether the auto navigation is enabled. When this property is true, click on
     *              other month date will automatically navigate to the previous or next month.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function enableAutoNavigation($value) {

        return $this->set('enableAutoNavigation', $value, 'bool');

    }

    /**
     * @detail      Sets a value indicating whether the tool tips are enabled.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function enableTooltips($value) {

        return $this->set('enableTooltips', $value, 'bool');

    }

    /**
     * @detail      Sets the tooltip text displayed when the mouse cursor is over the back navigation button.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function backText($value) {

        return $this->set('backText', $value, 'string');

    }

    /**
     * @detail      Sets the tooltip text displayed when the mouse cursor is over the forward navigation button.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function forwardText($value) {

        return $this->set('forwardText', $value, 'string');

    }

    /**
     * @detail      Add a special date to the Calendar.
     *
     * @param       bool $value COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function specialDates($value) {

        return $this->set('specialDates', $value, 'string');

    }

    /**
     * @detail      Execute JavaScript when the calendar back button is clicked
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function onBackButtonClick($code) {

        return $this->event('backButtonClick', $code);

    }

    /**
     * @detail      Execute JavaScript when the calendar next button is clicked
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function onNextButtonClick($code) {

        return $this->event('nextButtonClick', $code);

    }

    /**
     * @detail      Execute JavaScript when the calendar value changes
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @detail      Execute JavaScript when the calendar view changes
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function onViewChange($code) {

        return $this->event('viewChange', $code);

    }

    /**
     * @detail      COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function navigateForward() {

        return $this->method('navigateForward');

    }

    /**
     * @detail      COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function navigateBackward() {

        return $this->method('navigateBackward');

    }

    /**
     * @detail      COMMENT
     *
     * @param       mixed $value The min date to set as either a string or \Hazaar\Date object.
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function setMinDate($value) {

        return $this->method('setMinDate', $this->getDateObject($value));

    }

    /**
     * @detail      COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function getMinDate() {

        return $this->method('getMinDate');

    }

    /**
     * @detail      COMMENT
     *
     * @param       mixed $value The max date to set as either a string or \Hazaar\Date object.
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function setMaxDate($value) {

        return $this->method('setMaxDate', $this->getDateObject($value));

    }

    /**
     * @detail      COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function getMaxDate() {

        return $this->method('getMaxDate');

    }

    /**
     * @detail      COMMENT
     *
     * @param       mixed $value The date to set as either a string or \Hazaar\Date object.
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function setDate($value) {

        return $this->method('setDate', $this->getDateObject($value));

    }

    /**
     * @detail      COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function getDate() {

        return $this->method('getDate');

    }

    /**
     * @detail      COMMENT
     *
     * @param       mixed $value The start date to set as either a string or \Hazaar\Date object.
     *
     * @param       mixed $value The end date to set as either a string or \Hazaar\Date object.
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function setRange($start, $end) {

        return $this->method('setRange', array(
            $this->getDateObject($value),
            $this->getDateObject($value)
        ));

    }

    /**
     * @detail      COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function getRange() {

        return $this->method('getRange');

    }

    /**
     * @detail      COMMENT
     *
     * @return      \\Hazaar\\Widget\\Calendar
     */
    public function refresh() {

        return $this->method('refresh');

    }

}
