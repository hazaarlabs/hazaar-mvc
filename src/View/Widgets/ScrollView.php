<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Basic button widget.
 *
 * @since           1.1
 */
class ScrollView extends Widget {

    /**
     * @detail      Initialise a button widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       string $label The label to display on the button.
     */
    function __construct($name, $panels = array()) {

        parent::__construct('div', $name, array('type' => 'button'), false, $panels);

    }

    /**
     * @detail      Sets or gets the jqxScrollView's buttonsOffset property. This property sets the offset from the
     *              default location of the navigation buttons.
     *
     * @since       1.1
     *
     * @param       int $x The X offset
     *
     * @param       int $y The Y offset
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function buttonsOffset($x = 0, $y = 0) {

        return $this->set('buttonsOffset', array(
            $x,
            $y
        ));

    }

    /**
     * @detail      Sets or gets the jqxScrollView's moveThreshold property. The moveThreshold property specifies how
     *              much the user should drag the current element to navigate to next/previous element. Values should be
     *              set from 0.1 to 1. 0.5 means 50% of the element's width.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function moveThreshold($value) {

        return $this->set('moveThreshold', $value, 'int');

    }

    /**
     * @detail      Sets or gets the jqxScrollView's currentPage property. The currentPage specifies the displayed
     *              element.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function currentPage($value) {

        return $this->set('currentPage', $value, 'int');

    }

    /**
     * @detail      Sets or gets the animationDuration property. Specifies the duration of the animation which starts
     *              when the current page is changed.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function animationDuration($value) {

        return $this->set('animationDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether the navigation buttons are visible.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function showButtons($value) {

        return $this->set('showButtons', $value, 'bool');

    }

    /**
     * @detail      Sets or gets whether the bounce effect is enabled when pages are changed.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function bounceEnabled($value) {

        return $this->set('bounceEnabled', $value, 'bool');

    }

    /**
     * @detail      Indicates whether the slideShow mode is enabled. In this mode, pages are changed automatically in a
     *              time interval.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function slideShow($value) {

        return $this->set('slideShow', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the duration in milliseconds of a time interval. The current page is changed when the
     *              time interval is elapsed.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function slideDuration($value) {

        return $this->set('slideDuration', $value, 'int');

    }

    /**
     * @detail      This event is triggered when the current page is changed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function pageChanged($code) {

        return $this->event('pageChanged', $code);

    }

    /**
     * @detail      Refreshes the widget.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */

    public function refresh() {

        return $this->method('refresh');

    }

    /**
     * @detail      Navigates to a page.
     *
     * @since       1.1
     *
     * @param       int $page The page to navigate to.
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function changePage($page) {

        return $this->method('changePage', $page);

    }

    /**
     * @detail      Navigates to the next page.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */

    public function forward() {

        return $this->method('refresh');

    }

    /**
     * @detail      Navigates to the previous page.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function back() {

        return $this->method('refresh');

    }

}
