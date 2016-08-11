<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          ListMenu widget.
 *
 * @since           1.1
 */
class ListMenu extends Widget {

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
            throw new \Exception('Currently, ListMenu widgets only support existing HTML elements.  Please use hashref names to indicate the element the ListMenu should apply to.');

        parent::__construct('div', $name, $params);

    }

    public function filterCallback($value) {

        return $this->set('filterCallback', $value);

    }

    public function roundedCorners($value) {

        return $this->set('roundedCorners', $value, 'bool');

    }

    public function showNavigationArrows($value) {

        return $this->set('showNavigationArrows', $value, 'bool');

    }

    public function alwaysShowNavigationArrows($value) {

        return $this->set('alwaysShowNavigationArrows', $value, 'bool');

    }

    public function placeHolder($value) {

        return $this->set('placeHolder', $value, 'string');

    }

    public function showFilter($value) {

        return $this->set('showFilter', $value, 'bool');

    }

    public function showHeader($value) {

        return $this->set('showHeader', $value, 'bool');

    }

    public function showBackButton($value) {

        return $this->set('showBackButton', $value, 'bool');

    }

    public function backLabel($value) {

        return $this->set('backLabel', $value, 'string');

    }

    public function animationType($value) {

        return $this->set('animationType', $value, 'string');

    }

    public function animationDuration($value) {

        return $this->set('animationDuration', $value, 'int');

    }

    public function headerAnimationDuration($value) {

        return $this->set('headerAnimationDuration', $value, 'int');

    }

    public function autoSeparators($value) {

        return $this->set('autoSeparators', $value, 'bool');

    }

    public function readOnly($value) {

        return $this->set('readOnly', $value, 'bool');

    }

    public function enableScrolling($value) {

        return $this->set('enableScrolling', $value, 'bool');

    }

    //Methods

    public function changePage($newpage) {

        return $this->method('back', $newpage);

    }

    public function back() {

        return $this->method('back');

    }

}
