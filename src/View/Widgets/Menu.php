<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Menu widget.
 *
 * @since           1.1
 */
class Menu extends Widget {

    private $content;

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
     * @detail      Sets the widget's data source. This can be either an array or a datasource.  Either way, the
     *              resulting array data can have the following fields:
     *
     *              * id - A DOM id to set on the item.  This will be accessible in events as event.args.id.
     *              * label - The label to set on the item.  Overrides html.
     *              * html - The html to use for the item.  Use this if you want more complex items rendered such as
     *              those with icons.
     *
     *              Example:
     *
     *              <pre><code class="php">
     *              array(
     *                  array('id' => 'mail', 'html' => $this->fontawesome->icon('envelope') . ' ' .
     * $this->html->span('Email')),
     *                  array('id' => 'calendar', 'label' => 'Calendar')
     *              );
     *              </code></pre>
     *
     *              All fields are optional.  Just keep in mind that without a label or html element, the text 'Item'
     *              will be displayed and without an 'id' field the id will be automatically generated and you may not
     *              end up knowing what item triggered an event.
     *
     * @since       1.1
     *
     * @param       mixed $source The data source.  Can be an array or a DataSource object.
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function source($source) {

        if($source instanceof DataAdapter) {

            $source->autoBind(TRUE)->async(FALSE);

            $this->script->add('var dataAdapter = ' . $source . '; console.log(dataAdapter.records); ');

            $source = '!dataAdapter.records';

        }

        return $this->set('source', $source);

    }

    /**
     * @detail      Sets or gets the menu's display mode.
     *
     *              Possible Values:
     *              * 'horizontal'
     *              * 'vertical'
     *              * 'popup'
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function mode($value) {

        return $this->set('mode', $value, 'string');

    }

    /**
     * @detail      Sets or gets the animation's easing to one of the JQuery's supported easings.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function easing($value) {

        return $this->set('easing', $value, 'string');

    }

    /**
     * @detail      Sets or gets the duration of the show animation.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function animationShowDuration($value) {

        return $this->set('animationShowDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets the duration of the hide animation.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function animationHideDuration($value) {

        return $this->set('animationHideDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets the delay before the start of the hide animation.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function animationHideDelay($value) {

        return $this->set('animationHideDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets the delay before the start of the show animation.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function animationShowDelay($value) {

        return $this->set('animationShowDelay', $value, 'int');

    }

    /**
     * @detail      Sets or gets the time interval after which all opened items will be closed. When you open a new sub
     *              menu, the interval is cleared. If you want to disable this automatic closing behavior of the jqxMenu,
     *              you need to set the autoCloseInterval property to 0.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function autoCloseInterval($value) {

        return $this->set('autoCloseInterval', $value, 'int');

    }

    /**
     * @detail      Auto-Sizes the jqxMenu's main items when the menu's mode is 'horizontal'.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function autoSizeMainItems($value) {

        return $this->set('autoSizeMainItems', $value, 'bool');

    }

    /**
     * @detail      Automatically closes the opened popups after a click.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function autoCloseOnClick($value) {

        return $this->set('autoCloseOnClick', $value, 'bool');

    }

    /**
     * @detail      Enables or disables the rounded corners. This setting has effectin browsers that support the
     *              'border-radius CSS setting.'
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function enableRoundedCorners($value) {

        return $this->set('enableRoundedCorners', $value, 'bool');

    }

    /**
     * @detail      Opens the Context Menu when the right-mouse button is pressed. When this property is set to false,
     *              the Open and Close functions can be used to open and close the Context Menu.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function autoOpenPopup($value) {

        return $this->set('autoOpenPopup', $value, 'bool');

    }

    /**
     * @detail      Opens the top level menu items when the user hovers them.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function autoOpen($value) {

        return $this->set('autoOpen', $value, 'bool');

    }

    /**
     * @detail      Enables or disables the hover state.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function enableHover($value) {

        return $this->set('enableHover', $value, 'bool');

    }

    /**
     * @detail      Opens an item after a click by the user.
     *
     * @since       1.1
     *
     * @param       string @value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function clickToOpen($value) {

        return $this->set('clickToOpen', $value, 'bool');

    }

    /**
     * @detail      This event is triggered when any of the jqxMenu Sub Menus is displayed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      string
     */
    public function onShown($code) {

        return $this->event('shown', $code);

    }

    /**
     * @detail      This event is triggered when any of the jqxMenu Sub Menus is closed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      string
     */
    public function onClosed($code) {

        return $this->event('closed', $code);

    }

    /**
     * @detail      This event is triggered when a menu item is clicked.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      string
     */
    public function onItemclick($code) {

        return $this->event('itemclick', $code);

    }

    /**
     * @detail      This event is triggered after the menu is initialized.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered
     *
     * @return      string
     */
    public function onInitialized($code) {

        return $this->event('initialized', $code);

    }

    /**
     * @detail      Closes a menu item.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function closeItem($item_id) {

        return $this->method('closeItem', $item_id);

    }

    /**
     * @detail      Opens a menu item
     *
     * @since       1.1
     *
     * @return      string
     */
    public function openItem($item_id) {

        return $this->method('openItem', $item_id);

    }

    /**
     * @detail      Sets the item's popup open direction
     *
     * @since       1.1
     *
     * @return      string
     */
    public function setItemOpenDirection($value) {

        return $this->method('setItemOpenDirection', $value);

    }

    /**
     * @detail      Closes the menu (only in context menu mode).
     *
     * @since       1.1
     *
     * @return      string
     */
    public function close() {

        return $this->method('close');

    }

    /**
     * @detail      Opens the menu(only in context menu mode).
     *
     * @since       1.1
     *
     * @return      string
     */
    public function open($left, $top) {

        return $this->method('open', $left, $top);

    }

}
