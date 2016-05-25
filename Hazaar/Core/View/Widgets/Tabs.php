<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Tabs widget.
 *
 * @since           1.1
 */
class Tabs extends Widget {

    private $tabs = array();

    /**
     * @detail      Initialise an Tabs widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       mixed $content The initial value of the input.
     *
     * @param       array $params Optional additional parameters
     */
    function __construct($name, $tabs = null, $params = array()) {

        parent::__construct('div', $name, $params);

        $this->tabs($tabs);

    }

    public function renderObject() {

        if($this->element) {

            $list = new \Hazaar\Html\Block('ul');

            $items = new \Hazaar\Html\Group();

            $this->element->add($list, $items);

            foreach($this->tabs as $tab) {

                if($tab instanceof Tab) {

                    $list->add($tab->label);

                    if($tab->source) {

                        $this->onSelected("var tabIndex = event.args.item; var tabs = $(this); var source = $(this).jqxTabs('getContentAt', tabIndex).attr('data-source'); if(source) $.get(source, function(data) { tabs.jqxTabs('setContentAt', tabIndex, data); })");

                    }

                    $items->add($tab);

                }

            }

        }

        return parent::renderObject();

    }

    public function tab($label, $content = null, $params = array()) {

        return $this->tabs[] = new Tab($label, $content, $params);

    }

    public function tabs($tabs) {

        if(is_array($tabs)) {

            foreach($tabs as $tab) {

                if(!array_key_exists('label', $tab) || !array_key_exists('html', $tab))
                    throw new \Exception('Adding tabs with an array requires both "label" and "html" elements.');

                $this->add($tab['label'], $tab['html']);

            }

        }

        return $this;

    }

    public function scrollAnimationDuration($value) {

        return $this->set('scrollAnimationDuration', $value, 'int');

    }

    public function enabledHover($value) {

        return $this->set('enabledHover', $value, 'bool');

    }

    public function collapsible($value) {

        return $this->set('collapsible', $value, 'bool');

    }

    public function animationType($value) {

        return $this->set('animationType', $value, 'string');

    }

    public function enableScrollAnimation($value) {

        return $this->set('enableScrollAnimation', $value, 'bool');

    }

    public function contentTransitionDuration($value) {

        return $this->set('contentTransitionDuration', $value, 'int');

    }

    public function toggleMode($value) {

        return $this->set('toggleMode', $value, 'string');

    }

    public function selectedItem($value) {

        return $this->set('selectedItem', $value, 'int');

    }

    public function position($value) {

        return $this->set('position', $value, 'string');

    }

    public function selectionTracker($value) {

        return $this->set('selectionTracker', $value, 'bool');

    }

    public function scrollable($value) {

        return $this->set('scrollable', $value, 'bool');

    }

    public function scrollPosition($value) {

        return $this->set('scrollPosition', $value, 'string');

    }

    public function scrollStep($value) {

        return $this->set('scrollStep', $value, 'int');

    }

    public function autoHeight($value) {

        return $this->set('autoHeight', $value, 'bool');

    }

    public function showCloseButtons($value) {

        return $this->set('showCloseButtons', $value, 'bool');

    }

    public function closeButtonSize($value) {

        return $this->set('closeButtonSize', $value, 'int');

    }

    public function initTabContent($value) {

        return $this->set('initTabContent', $value);

    }

    public function keyboardNavigation($value) {

        return $this->set('keyboardNavigation', $value, 'bool');

    }

    public function reorder($value) {

        return $this->set('reorder', $value, 'bool');

    }

    public function enableDropAnimation($value) {

        return $this->set('enableDropAnimation', $value, 'bool');

    }

    public function dropAnimationDuration($value) {

        return $this->set('dropAnimationDuration', $value, 'int');

    }

    //Events

    public function onCreated($code) {

        return $this->event('created', $code);

    }

    public function onSelected($code) {

        return $this->event('selected', $code);

    }

    public function onTabclick($code) {

        return $this->event('tabclick', $code);

    }

    public function onAdd($code) {

        return $this->event('add', $code);

    }

    public function onRemoved($code) {

        return $this->event('removed', $code);

    }

    public function onEnable($code) {

        return $this->event('enable', $code);

    }

    public function onDisable($code) {

        return $this->event('disable', $code);

    }

    public function onSelecting($code) {

        return $this->event('selecting', $code);

    }

    public function onUnselecting($code) {

        return $this->event('unselecting', $code);

    }

    public function onUnselected($code) {

        return $this->event('unselected', $code);

    }

    public function onDragStart($code) {

        return $this->event('dragStart', $code);

    }

    public function onDragEnd($code) {

        return $this->event('dragEnd', $code);

    }

    public function onLocked($code) {

        return $this->event('expanded', $code);

    }

    public function onUnlocked($code) {

        return $this->event('unlocked', $code);

    }

    public function onCollapsed($code) {

        return $this->event('collapsed', $code);

    }

    public function onExpanded($code) {

        return $this->event('expanded', $code);

    }

    //Methods

    public function removeAt() {

        return $this->method('removeAt');

    }

    public function removeFirst() {

        return $this->method('removeFirst');

    }

    public function removeLast() {

        return $this->method('removeLast');

    }

    public function collapse() {

        return $this->method('collapse');

    }

    public function expand() {

        return $this->method('expand');

    }

    public function disableAt() {

        return $this->method('disableAt');

    }

    public function enableAt() {

        return $this->method('enableAt');

    }

    public function addAt() {

        return $this->method('addAt');

    }

    public function addFirst() {

        return $this->method('addFirst');

    }

    public function addLast() {

        return $this->method('addLast');

    }

    public function select() {

        return $this->method('select');

    }

    public function length() {

        return $this->method('length');

    }

    public function setContentAt() {

        return $this->method('setContentAt');

    }

    public function getContentAt() {

        return $this->method('getContentAt');

    }

    public function setTitleAt() {

        return $this->method('setTitleAt');

    }

    public function getTitleAt() {

        return $this->method('getTitleAt');

    }

    public function showCloseButtonAt() {

        return $this->method('showCloseButtonAt');

    }

    public function hideCloseButtonAt() {

        return $this->method('hideCloseButtonAt');

    }

    public function hideAllCloseButtons() {

        return $this->method('hideAllCloseButtons');

    }

    public function showAllCloseButtons() {

        return $this->method('showAllCloseButtons');

    }

    public function ensureVisible() {

        return $this->method('ensureVisible');

    }

    public function isDisabled() {

        return $this->method('isDisabled');

    }

}
