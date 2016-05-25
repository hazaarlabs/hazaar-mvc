<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          ListMenu widget.
 *
 * @since           1.1
 */
class NavigationBar extends Widget {

    private $items = array();

    /**
     * @detail      Initialise an ListMenu widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       mixed $items The initial items of the input.
     *
     * @param       array $params Optional additional parameters
     */
    function __construct($name, $items = null, $params = array()) {

        if(substr($name, 0, 1) != '#')
            throw new \Exception('Currently, NavigationBar widgets only support existing HTML elements.  Please use hashref names to indicate the element the NavigationBar should apply to.');

        parent::__construct('div', $name, $params);

    }

    /**
     * @detail      Sets or gets the expanding animation duration.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function expandAnimationDuration($value) {

        return $this->set('expandAnimationDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets the collapsing animation duration.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function collapseAnimationDuration($value) {

        return $this->set('collapseAnimationDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets the animation type.
     *
     *              Possible Values:
     *              * 'slide'
     *              * 'fade'
     *              * 'none'
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function animationType($value) {

        return $this->set('animationType', $value, 'string');

    }

    /**
     * @detail      Sets or gets user interaction used for expanding or collapsing the content. Possible values ['click',
     *              'dblclick', 'none'].
     *
     *              Possible Values:
     *              * 'click'
     *              * 'dblclick'
     *              * 'none'
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function toggleMode($value) {

        return $this->set('toggleMode', $value, 'string');

    }

    /**
     * @detail      Sets or gets navigation bar's expand mode. Possible values ['single', 'singleFitHeight' 'multiple',
     *              'toggle', 'none'].
     *
     *              Possible Values:
     *              * 'single' - only one item can be expanded. If the expanded item's height is greater than the value
     *              of the height property, a vertical scrollbar is shown.
     *              * 'singleFitHeight' - only one item can be expanded. If the expanded item's height is greater than
     *              the value of the height property, a vertical scrollbar is shown inside the content of the expanded
     *              item
     *              * 'multiple' - multiple items can be expanded. If the expanded items' height is greater than the
     *              value of the height property, a vertical scrollbar is shown.
     *              *  'toggle' - only one item can be expanded. The expanded item can also be collapsed.If the expanded
     *              item's height is greater than the value of the height property, a vertical scrollbar is shown
     *              * 'none' - no items can be expanded/collapsed
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function expandMode($value) {

        return $this->set('expandMode', $value, 'string');

    }

    /**
     * @detail      Sets or gets header's arrow position.
     *
     *              Possible Values:
     *              * 'left'
     *              * 'right'
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function arrowPosition($value) {

        return $this->set('arrowPosition', $value, 'string');

    }

    /**
     * @detail      Sets or gets whether header's arrow is going to be shown.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function showArrow($value) {

        return $this->set('showArrow', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the expanded item(s). If the property expandMode is set to either 'single',
     *              'singleFitHeight', 'toggle' or 'none', only the item corresponding to the first value in the array is
     *              expanded. If the property expandMode is set to either 'single' or 'singleFitHeight' and the
     *              expandedIndexes array is empty, the first item is expanded automatically.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function expandedIndexes($value) {

        return $this->set('expandedIndexes', $value);

    }

    /**
     * @detail      Callback function called when an item's content needs to be initialized. Useful for initializing
     *              other widgets within the content of any of the items of jqxNavigationBar. The index argument shows
     *              which item is initialized.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function initContent($value) {

        return $this->set('initContent', $value);

    }

    /**
     * @detail      This event is triggered when a jqxNavigationBar item is going to be expanded.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function onExpandingItem($code) {

        return $this->event('expandingItem', $code);

    }

    /**
     * @detail      This event is triggered when a jqxNavigationBar item is expanded.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function onExpandedItem($code) {

        return $this->event('expandedItem', $code);

    }

    /**
     * @detail      This event is triggered when a jqxNavigationBar item is going to be collapsed.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function onCollapsingItem($code) {

        return $this->event('collapsingItem', $code);

    }

    /**
     * @detail      This event is triggered when a jqxNavigationBar item is collapsed.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function onCollapsedItem($code) {

        return $this->event('collapsedItem', $code);

    }

    /**
     * @detail      Collapsing item with any index.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function collapseAt($index) {

        return $this->method('collapseAt', $index);

    }

    /**
     * @detail      Expanding item with any index.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function expandAt($index) {

        return $this->method('expandAt', $index);

    }

    /**
     * @detail      Setting content to item with any index.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function setContentAt($index, $content) {

        return $this->method('setContentAt', $index, $content);

    }

    /**
     * @detail      Setting header content to item with any index
     *
     * @since       1.1
     *
     * @return      string
     */
    public function setHeaderContentAt($index, $header) {

        return $this->method('setHeaderContentAt', $index, $header);

    }

    /**
     * @detail      Getting header content of item with any index. Returns a string value.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getHeaderContentAt($index) {

        return $this->method('getHeaderContentAt', $index);

    }

    /**
     * @detail      Getting content of item with any index. Returns a string value.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getContentAt($index) {

        return $this->method('getContentAt', $index);

    }

    /**
     * @detail      Disabling item with any index.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function disableAt($index) {

        return $this->method('disableAt', $index);

    }

    /**
     * @detail      Enabling item with any index.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function enableAt($index) {

        return $this->method('enableAt', $index);

    }

    /**
     * @detail      Showing the arrow of an item with specific index.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function showArrowAt($index) {

        return $this->method('showArrowAt', $index);

    }

    /**
     * @detail      Hiding the arrow of an item with specific index.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function hideArrowAt($index) {

        return $this->method('hideArrowAt', $index);

    }

    /**
     * @detail      This method refreshes the navigationbar.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function invalidate() {

        return $this->method('invalidate');

    }

    /**
     * @detail      This method refreshes the navigationbar.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function refresh() {

        return $this->method('refresh');

    }

    /**
     * @detail      This method renders the navigationbar.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function render() {

        return $this->method('render');

    }

    /**
     * @detail      This method inserts an item at a specific index. It has three parameters - index, header (the header
     *              of the new item) and content (the content of the new item).
     *
     * @since       1.1
     *
     * @return      string
     */
    public function insert($header, $content) {

        return $this->method('insert', $header, $content);

    }

    /**
     * @detail      This method inserts an item at the bottom of the navigationbar. It has two parameters - header (the
     *              header of the new item) and content (the content of the new item).
     *
     * @since       1.1
     *
     * @return      string
     */
    public function addItem($header, $content) {

        return $this->method('add', $header, $content);

    }

    /**
     * @detail      This method updates an item at a specific index. It has three parameters - index, header (the new
     *              header of the item) and content (the new content of the item).
     *
     * @since       1.1
     *
     * @return      string
     */
    public function update($index, $header = null, $content = null) {

        return $this->method('update', $index, $header, $content);

    }

}
