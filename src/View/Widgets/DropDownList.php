<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          DropDownList button widget.
 *
 * @since           1.1
 */
class DropDownList extends Widget {

    /**
     * @detail      Initialise a DropDownList widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       string $value The initial value of the input.
     */
    function __construct($name, $params = array()) {

        parent::__construct('div', $name, $params);

    }

    /**
     * @detail      Sets or gets the selected index.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function selectedIndex($value) {

        return $this->set('selectedIndex', $value, 'int');

    }

    /**
     * @detail      Sets or gets the items source.  The source can be either a DataAdapter object or an array of data
     *              values.
     *
     * @since       1.1
     *
     * @param       mixed $source The DataSource object that defines where data is coming from.
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function source($source, $root = null, JavaScript $map = null, JavaScript $formatData = null) {

        if(!is_array($source) && !$source instanceof DataAdapter) {

            throw new \Exception('Source MUST be an array or a data adapter!');

        }

        return $this->set('source', $source);

    }

    /**
     * @detail      Sets or gets the displayMember of the Items. The displayMember specifies the name of an object
     *              property to display. The name is contained in the collection specified by the 'source' property.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function displayMember($value) {

        return $this->set('displayMember', $value, 'string');

    }

    /**
     * @detail      Sets or gets the valueMember of the Items. The valueMember specifies the name of an object property
     *              to set as a 'value' of the list items. The name is contained in the collection specified by the
     *              'source' property.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function valueMember($value) {

        return $this->set('valueMember', $value, 'string');

    }

    /**
     * @detail      Sets or gets the popup's z-index.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function popupZIndex($value) {

        return $this->set('popupZIndex', $value, 'int');

    }

    /**
     * @detail      Determines whether checkboxes will be displayed next to the list items. (The feature requires
     *              jqxcheckbox.js)
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function checkboxes($value) {

        return $this->set('checkboxes', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the scrollbars size.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function scrollBarSize($value) {

        return $this->set('scrollBarSize', $value, 'int');

    }

    /**
     * @detail      Enables/disables the hover state.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function enableHover($value) {

        return $this->set('enableHover', $value, 'bool');

    }

    /**
     * @detail      Text displayed when the selection is empty.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function placeHolder($value) {

        return $this->set('placeHolder', $value, 'string');

    }

    /**
     * @detail      Sets or gets the incrementalSearch property. An incremental search begins searching as soon as you
     *              type the first character of the search string. As you type in the search string, jqxDropDownList
     *              automatically selects the found item.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function incrementalSearch($value) {

        return $this->set('incrementalSearch', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the incrementalSearchDelay property. The incrementalSearchDelay specifies the
     *              time-interval in milliseconds after which the previous search string is deleted. The timer starts
     *              when you stop typing.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function incrementalSearchDelay($value) {

        return $this->set('incrementalSearchDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets the item incremental search mode. When the user types some text in a focused
     * DropDownList, the jqxListBox widget tries to find the searched item using the entered text and the selected search
     * mode.
     *
     *              Possible Values:
     *              * 'none'
     *              * 'contains'
     *              * 'containsignorecase'
     *              * 'equals'
     *              * 'equalsignorecase'
     *              * 'startswithignorecase'
     *              * 'startswith'
     *              * 'endswithignorecase'
     *              * 'endswith'
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function searchMode($value) {

        return $this->set('searchMode', $value, 'string');

    }

    /**
     * @detail      Enables/disables the selection.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function enableSelection($value) {

        return $this->set('enableSelection', $value, 'bool');

    }

    /**
     * @detail      Sets or gets whether the dropdown detects the browser window's bounds and automatically adjusts the
     *              dropdown's position.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function enableBrowserBoundsDetection($value) {

        return $this->set('enableBrowserBoundsDetection', $value, 'bool');

    }

    /**
     * @detail      Sets or gets whether the DropDown is automatically opened when the mouse cursor is moved over the
     *              widget.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function autoOpen($value) {

        return $this->set('autoOpen', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the DropDown's alignment.
     *
     *              Possible Values:
     *              * 'left'
     *              * 'right'
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function dropDownHorizontalAlignment($value) {

        return $this->set('dropDownHorizontalAlignment', $value, 'string');

    }

    /**
     * @detail      Sets or gets the height of the jqxDropDownList's ListBox displayed in the widget's DropDown.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function dropDownHeight($value) {

        return $this->set('dropDownHeight', $value, 'int');

    }

    /**
     * @detail      Sets or gets the width of the jqxDropDownList's ListBox displayed in the widget's DropDown.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function dropDownWidth($value) {

        return $this->set('dropDownWidth', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether the height of the jqxDropDownList's ListBox displayed in the widget's DropDown
     *              is calculated as a sum of the items heights.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function autoDropDownHeight($value) {

        return $this->set('autoDropDownHeight', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the height of the jqxDropDownList Items. When the itemHeight == - 1, each item's height
     *              is equal to its desired height.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function itemHeight($value) {

        return $this->set('itemHeight', $value, 'int');

    }

    /**
     * @detail      Callback function which is called when an item is rendered. By using the renderer function, you can
     *              customize the look of the list items.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function renderer($value) {

        if(!$value instanceof JavaScript)
            $value = new JavaScript($value, array(
                'index',
                'label',
                'value'
            ));

        return $this->set('renderer', $value);

    }

    /**
     * @detail      Callback function which is called when the selected item is rendered in the jqxDropDownList's content
     *              area. By using the selectionRenderer function, you can customize the look of the selected item.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function selectionRenderer($value) {

        return $this->set('selectionRenderer', $value);

    }

    /**
     * @detail      Sets or gets the delay of the 'open' animation.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function openDelay($value) {

        return $this->set('openDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets the delay of the 'close' animation.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
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
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function animationType($value) {

        return $this->set('animationType', $value, 'string');

    }

    /**
     * @detail      This event is triggered when the user selects an item.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function onSelect($code) {

        return $this->event('select', $code);

    }

    /**
     * @detail      This event is triggered when the user unselects an item.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function onUnselect($code) {

        return $this->event('unselect', $code);

    }

    /**
     * @detail      This event is triggered when the user selects an item.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @detail      This event is triggered when the popup ListBox is opened.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function onOpen($code) {

        return $this->event('open', $code);

    }

    /**
     * @detail      This event is triggered when the popup ListBox is closed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function onClose($code) {

        return $this->event('close', $code);

    }

    /**
     * @detail      This event is triggered when an item is checked/unchecked.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function onCheckChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @detail      This event is triggered when the data binding operation is completed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function onBindingComplete($code) {

        return $this->event('bindingcomplete', $code);

    }

    /**
     * @detail      Adds a new item to the jqxDropDownList. Returns 'true', if the new item is added or false if the item
     *              is not added.
     *
     * @since       1.1
     *
     * @param       string $item
     *
     * @return      string
     */
    public function addItem($item) {

        return $this->method('addItem', $item);

    }

    /**
     * @detail      Inserts a new item to the jqxDropDownList.
     *
     * @since       1.1
     *
     * @param       string $item
     *
     * @param       int $index
     *
     * @return      string
     */
    public function insertAt($item, $index) {

        return $this->method('insertAt', $item, $index);

    }

    /**
     * @detail      Removes an item from the jqxDropDownList. Index is a number of the item to remove
     *
     * @since       1.1
     *
     * @param       int $index
     *
     * @return      string
     */
    public function removeAt($index) {

        return $this->method('removeAt', $index);

    }

    /**
     * @detail      Disables an item by index. Index is a number.
     *
     * @since       1.1
     *
     * @param       int $index
     *
     * @return      string
     */
    public function disableAt($index) {

        return $this->method('disableAt', $index);

    }

    /**
     * @detail      Enables a disabled item by index. Index is a number.
     *
     * @since       1.1
     *
     * @param       int $index
     *
     * @return      string
     */
    public function enableAt($index) {

        return $this->method('enableAt', $index);

    }

    /**
     * @detail      Ensures that an item is visible. index is number. When necessary, the jqxDropDownList scrolls to the
     *              item to make it visible.
     *
     * @since       1.1
     *
     * @param       int $index
     *
     * @return      string
     */
    public function ensureVisible($index) {

        return $this->method('ensureVisible', $index);

    }

    /**
     * @detail      Gets item by index.
     *
     *              The returned value is an Object with the following fields:
     *              * label - gets item's label.
     *              * value - gets the item's value.
     *              * disabled - gets whether the item is enabled/disabled.
     *              * checked - gets whether the item is checked/unchecked.
     *              * hasThreeStates - determines whether the item's checkbox supports three states.
     *              * html - gets the item's display html. This can be used instead of label.
     *              * index - gets the item's index.
     *              * group - gets the item's group.
     *
     * @since       1.1
     *
     * @param       string $value
     *
     * @return      string
     */
    public function getItem($index) {

        return $this->method('getItem', $index);

    }

    /**
     * @detail      Gets an item by its value.
     *
     *              The returned value is an Object with the following fields:
     *              * label - gets item's label.
     *              * value - gets the item's value.
     *              * disabled - gets whether the item is enabled/disabled.
     *              * checked - gets whether the item is checked/unchecked.
     *              * hasThreeStates - determines whether the item's checkbox supports three states.
     *              * html - gets the item's display html. This can be used instead of label.
     *              * index - gets the item's index.
     *              * group - gets the item's group.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getItemByValue($value) {

        return $this->method('getItemByValue', $value);

    }

    /**
     * @detail      Gets all items. The returned value is an array of Items.
     *
     *              Each item represents an Object with the following fields:
     *              * label - gets item's label.
     *              * value - gets the item's value.
     *              * disabled - gets whether the item is enabled/disabled.
     *              * checked - gets whether the item is checked/unchecked.
     *              * hasThreeStates - determines whether the item's checkbox supports three states.
     *              * html - gets the item's display html. This can be used instead of label.
     *              * index - gets the item's index.
     *              * group - gets the item's group.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getItems() {

        return $this->method('getItems');

    }

    /**
     * @detail      Gets the checked items. The returned value is an array of Items.

     *              Each item represents an Object with the following fields:
     *              * label - gets item's label.
     *              * value - gets the item's value.
     *              * disabled - gets whether the item is enabled/disabled.
     *              * checked - gets whether the item is checked/unchecked.
     *              * hasThreeStates - determines whether the item's checkbox supports three states.
     *              * html - gets the item's display html. This can be used instead of label.
     *              * index - gets the item's index.
     *              * group - gets the item's group.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getCheckedItems() {

        return $this->method('getCheckedItems');

    }

    /**
     * @detail      Gets the selected item. The returned value is an Object or null(if there is no selected item).
     *
     *              Item Fields:
     *              * label - gets item's label.
     *              * value - gets the item's value.
     *              * disabled - gets whether the item is enabled/disabled.
     *              * checked - gets whether the item is checked/unchecked.
     *              * hasThreeStates - determines whether the item's checkbox supports three states.
     *              * html - gets the item's display html. This can be used instead of label.
     *              * index - gets the item's index.
     *              * group - gets the item's group.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getSelectedItem() {

        return $this->method('getSelectedItem');

    }

    /**
     * @detail      Gets the index of the selected item. The returned value is the index of the selected item. If there's
     *              no selected item, -1 is returned.
     *
     * @since       1.1
     *
     * @param       string $value
     *
     * @return      string
     */
    public function getSelectedIndex() {

        return $this->method('getSelectedIndex');

    }

    /**
     * @detail      Selects an item by index. The index is zero-based, i.e to select the first item, the 'selectIndex'
     *              method should be called with parameter 0.
     *
     * @since       1.1
     *
     * @param       int $index
     *
     * @return      string
     */
    public function selectIndex($index) {

        return $this->method('selectIndex', $index);

    }

    /**
     * @detail      Unselects item by index. The index is zero-based, i.e to unselect the first item, the 'unselectIndex'
     *              method should be called with parameter 0.
     *
     * @since       1.1
     *
     * @param       object $item
     *
     * @return      string
     */
    public function unselectIndex($index) {

        return $this->method('unselectIndex', $index);

    }

    /**
     * @detail      Selects an item.
     *
     * @since       1.1
     *
     * @param       object $item
     *
     * @return      string
     */
    public function selectItem($item) {

        return $this->method('selectItem', $item);

    }

    /**
     * @detail      Unselects an item.
     *
     * @since       1.1
     *
     * @param       object $item
     *
     * @return      string
     */
    public function unselectItem($item) {

        return $this->method('unselectItem', $item);

    }

    /**
     * @detail      Clears all selected items.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function clearSelection() {

        return $this->method('clearSelection');

    }

    /**
     * @detail      Clears all items.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function clear() {

        return $this->method('clear');

    }

    /**
     * @detail      Hides the popup listbox.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function close() {

        return $this->method('close');

    }

    /**
     * @detail      Shows the popup listbox.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function open() {

        return $this->method('open');

    }

    /**
     * @detail      Returns true, if the popup is opened. Otherwise returns false.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function isOpened() {

        return $this->method('isOpened');

    }

    /**
     * @detail      Checks a list item when the 'checkboxes' property value is true. The index is zero-based, i.e to
     *              check the first item, the 'checkIndex' method should be called with parameter 0.
     *
     * @since       1.1
     *
     * @param       int $index
     *
     * @return      string
     */
    public function checkIndex($index) {

        return $this->method('checkIndex', $index);

    }

    /**
     * @detail      Unchecks a list item when the 'checkboxes' property value is true. The index is zero-based, i.e to
     *              uncheck the first item, the 'uncheckIndex' method should be called with parameter 0.
     *
     * @since       1.1
     *
     * @param       int $index
     *
     * @return      string
     */
    public function uncheckIndex($index) {

        return $this->method('uncheckIndex', $index);

    }

    /**
     * @detail      indeterminates a list item when the 'checkboxes' property value is true. The index is zero-based, i.e
     *              to indeterminate the first item, the 'indeterminateIndex' method should be called with parameter 0.
     *
     * @since       1.1
     *
     * @param       object $item
     *
     * @return      string
     */
    public function indeterminateIndex($index) {

        return $this->method('indeterminateIndex', $index);

    }

    /**
     * @detail      Checks an item.
     *
     * @since       1.1
     *
     * @param       object $item
     *
     * @return      string
     */
    public function checkItem($item) {

        return $this->method('checkItem', $item);

    }

    /**
     * @detail      Unchecks an item.
     *
     * @since       1.1
     *
     * @param       object $item
     *
     * @return      string
     */
    public function uncheckItem($item) {

        return $this->method('uncheckItem', $item);

    }

    /**
     * @detail      Indeterminates an item.
     *
     * @since       1.1
     *
     * @param       object $item
     *
     * @return      string
     */
    public function indeterminateItem($item) {

        return $this->method('indeterminateItem', $item);

    }

    /**
     * @detail      Checks all list items when the 'checkboxes' property value is true.
     *
     * @since       1.1
     *
     * @param       object $item
     *
     * @return      string
     */
    public function checkAll($item) {

        return $this->method('checkAll', $item);

    }

    /**
     * @detail      Unchecks all list items when the 'checkboxes' property value is true.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function uncheckAll() {

        return $this->method('uncheckAll');

    }

    /**
     * @detail      Sets the content of the DropDownList.
     *
     * @since       1.1
     *
     * @param       string $content
     *
     * @return      string
     */
    public function setContent($content) {

        return $this->method('setContent', $content);

    }

    /**
     * @detail      Loads list items from a 'select' tag.
     *
     * @since       1.1
     *
     * @param       object $select
     *
     * @return      string
     */
    public function loadFromSelect($select) {

        return $this->method('loadFromSelect', $select);

    }

    /**
     * @detail      Sets or gets the selected value.
     *
     * @since       1.1
     *
     * @param       string $value
     *
     * @return      string
     */
    public function val($value = null) {

        return $this->method('val', $value);

    }

}
