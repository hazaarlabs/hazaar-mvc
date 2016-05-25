<?php

namespace Hazaar\View\Widgets;

/**
 * @detail      Tree widget.
 *
 * @since       1.1
 */
class Tree extends Widget {

    private $list;

    /**
     * @detail      Initialise a checkbox widget
     *
     * @param       string $id The ID of the button element to create.
     */
    function __construct($name) {

        $this->list = new \Hazaar\Html\Block('ul');

        parent::__construct('div', $name, null, false, $this->list);

    }

    public function items($items) {

        if($items instanceof TreeItem) {

            $this->list->add($items);

        } elseif(is_array($items)) {

            if(array_key_exists('name', $items) && array_key_exists('html', $items)) {

                $expanded = (array_key_exists('expanded', $items) ? $items['expanded'] : null);

                $params = (array_key_exists('params', $items) ? $items['params'] : null);

                $item = new TreeItem($items['name'], $items['html'], $expanded, $params);

                $this->items($item);

                if(array_key_exists('items', $items)) {

                    $item->items($items['items']);

                }

            } else {

                foreach($items as $item) {

                    $this->items($item);

                }

            }

        }

        return $this;

    }

    public function easing($value) {

        return $this->set('easing', $value, 'string');

    }

    public function animationShowDuration($value) {

        return $this->set('animationShowDuration', $value, 'int');

    }

    public function animationHideDuration($value) {

        return $this->set('animationHideDuration', $value, 'int');

    }

    public function enableHover($value) {

        return $this->set('enableHover', $value, 'bool');

    }

    public function keyboardNavigation($value) {

        return $this->set('keyboardNavigation', $value, 'bool');

    }

    public function toggleMode($value) {

        return $this->set('toggleMode', $value, 'string');

    }

    public function source($value) {

        return $this->set('source', $value);

    }

    public function checkboxes($value) {

        return $this->set('checkboxes', $value, 'bool');

    }

    public function hasThreeStates($value) {

        return $this->set('hasThreeStates', $value, 'bool');

    }

    public function allowDrag($value) {

        return $this->set('allowDrag', $value, 'bool');

    }

    public function allowDrop($value) {

        return $this->set('allowDrop', $value, 'bool');

    }

    public function dragStart($value) {

        return $this->set('dragStart', $value);

    }

    public function dragEnd($value) {

        return $this->set('dragEnd', $value);

    }

    public function toggleIndicatorSize($value) {

        return $this->set('toggleIndicatorSize', $value, 'int');

    }

    public function onExpand($code) {

        return $this->event('expand', $code);

    }

    public function onCollapse($code) {

        return $this->event('collapse', $code);

    }

    public function onSelect($code) {

        return $this->event('select', $code);

    }

    public function onInitialized($code) {

        return $this->event('initialized', $code);

    }

    public function onAdd($code) {

        return $this->event('add', $code);

    }

    public function onRemoved($code) {

        return $this->event('removed', $code);

    }

    public function onCheckChange($code) {

        return $this->event('checkChange', $code);

    }

    public function onDragStart($code) {

        return $this->event('dragStart', $code);

    }

    public function onDragEnd($code) {

        return $this->event('dragEnd', $code);

    }

    public function ensureVisible($element) {

        return $this->method('ensureVisible', $element);

    }

    public function addTo($item, $parent = null) {

        return $this->method('addTo', $item);

    }

    public function removeItem($element) {

        return $this->method('removeItem', $element);

    }

    public function clear() {

        return $this->method('clear');

    }

    public function disableItem($element) {

        return $this->method('disableItem', $element);

    }

    public function checkAll() {

        return $this->method('checkAll');

    }

    public function uncheckAll() {

        return $this->method('uncheckAll');

    }

    public function checkItem($element, $state) {

        return $this->method('checkItem', $element, $state);

    }

    public function uncheckItem($element) {

        return $this->method('uncheckItem', $element);

    }

    public function enableItem($element) {

        return $this->method('enableItem', $element);

    }

    public function getCheckedItems() {

        return $this->method('getCheckedItems');

    }

    public function getUncheckedItems() {

        return $this->method('getUncheckedItems');

    }

    public function getItems() {

        return $this->method('getItems');

    }

    public function getItem($element) {

        return $this->method('getItem', $element);

    }

    public function getSelectedItem() {

        return $this->method('getSelectedItem');

    }

    public function getPrevItem($element) {

        return $this->method('getPrevItem', $element);

    }

    public function getNextItem($element) {

        return $this->method('getNextItem', $element);

    }

    public function selectItem($element) {

        return $this->method('selectItem', $element);

    }

    public function collapseAll() {

        return $this->method('collapseAll');

    }

    public function expandAll() {

        return $this->method('expandAll');

    }

    public function collapseItem($element) {

        return $this->method('collapseItem', $element);

    }

    public function expandItem($element) {

        return $this->method('expandItem', $element);

    }

    public function refresh() {

        return $this->method('refresh');

    }

}
