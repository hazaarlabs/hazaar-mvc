<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          DropDownList button widget.
 *
 * @since           1.1
 */
class ListBox extends Widget {

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
     * @detail      Enables/disables the multiple selection. When this property is set to true, the user can select
     *              multiple items.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function multiple($value) {

        return $this->set('multiple', $value, 'bool');

    }

    /**
     * @detail      Enables/disables the multiple selection using Shift and Ctrl keys. When this property is set to true,
     *              the user can select multiple items by clicking on item and holding Shift or Ctrl.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function multipleextended($value) {

        return $this->set('multipleextended', $value, 'bool');

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
     * @detail      Sets or gets whether the items width should be equal to the listbox's width.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function equalItemsWidth($value) {

        return $this->set('equalItemsWidth', $value, 'bool');

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
     * @detail      Sets or gets whether the listbox's height is equal to the sum of each item's height
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function autoHeight($value) {

        return $this->set('autoHeight', $value, 'bool');

    }

    /**
     * @detail      Sets or gets whether the checkboxes have three states - checked, unchecked and indeterminate.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function hasThreeStates($value) {

        return $this->set('hasThreeStates', $value, 'bool');

    }

    /**
     * @detail      Enables/disables the dragging of ListBox Items.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function allowDrag($value) {

        return $this->set('allowDrag', $value, 'bool');

    }

    /**
     * @detail      Enables/disables the dropping of ListBox Items.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function allowDrop($value) {

        return $this->set('allowDrop', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the drop action when an item is dropped.
     *
     *              Possible Values:
     *              * 'default'
     *              * 'copy'-adds a clone of the dropped item
     *              * 'none'-means that the dropped item will not be added to a new collection and will not be removed
     *              from its parent collection
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function dropAction($value) {

        return $this->set('dropAction', $value, 'string');

    }

    /**
     * @detail      Callback function which is called when a drag operation starts.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function dragStart($value) {

        return $this->set('autoHeight', $value);

    }

    /**
     * @detail      Callback function which is called when a drag operation ends.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function dragEnd($value) {

        return $this->set('autoHeight', $value);

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
     * @detail      This event is triggered when the user starts a drag operation.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function onDragStart($code) {

        return $this->event('dragStart', $code);

    }

    /**
     * @detail      This event is triggered when the user drops an item.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function onDragEnd($code) {

        return $this->event('dragEnd', $code);

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
    public function getSelectedItems() {

        return $this->method('getSelectedItems');

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
     * @detail      Stops the ListBox's rendering. That method is usually used when multiple items need to be inserted or
     *              removed dynamically.
     *
     * @since       1.1
     *
     * @param       object $select
     *
     * @return      string
     */
    public function beginUpdate($select) {

        return $this->method('beginUpdate', $select);

    }

    /**
     * @detail      Starts the ListBox's rendering and refreshes the ListBox. That method is usually used in combination
     *              with the 'beginUpdate' method when multiple items need to be inserted or removed dynamically.
     *
     * @since       1.1
     *
     * @param       object $select
     *
     * @return      string
     */
    public function endUpdate($select) {

        return $this->method('endUpdate', $select);

    }

    /**
     * @detail      The invalidate method will repaint the jqxListBox's items.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function invalidate() {

        return $this->method('invalidate');

    }

}
