<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          ListMenu widget.
 *
 * @since           1.1
 */
class Docking extends Widget {

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
            throw new \Exception('Currently, Docking widgets only support existing HTML elements.  Please use hashref names to indicate the element the Docking should apply to.');

        parent::__construct('div', $name, $params);

    }

    /**
     * @detail      Sets or gets docking's orientation. This property is setting whether the panels are going to be side
     *              by side or below each other.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function orientation($value) {

        return $this->set('orientation', $value, 'string');

    }

    /**
     * @detail      Sets or gets docking's mode.
     *
     *              Possible Values:
     *              * 'default'-the user can  drop every window inside any docking panel or outside the docking panels
     *              * 'docked'-the user can drop every window just into the docking panels
     *              * 'floating'-the user can drop any window just outside of the docking panels.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function mode($value) {

        return $this->set('mode', $value, 'string');

    }

    /**
     * @detail      Sets or gets the opacity of the window which is currently dragged by the user.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function floatingWindowOpacity($value) {

        return $this->set('floatingWindowOpacity', $value, 'float');

    }

    /**
     * @detail      Set or gets whether the panels of the docking are going to be with rounded corners.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function panelsRoundedCorners($value) {

        return $this->set('panelsRoundedCorners', $value, 'bool');

    }

    /**
     * @detail      Sets ot gets specific mode for each window. The value of the property is object with keys - window's
     *              ids and values - specific modes.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function windowsMode($value) {

        return $this->set('windowsMode', $value, 'string');

    }

    /**
     * @detail      Enables or disables the cookies. If the cookies are enabled then the docking layout is going to be
     *              saved and kept every time the page is being reloaded.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function cookies($value) {

        return $this->set('cookies', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the cookie options.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function cookieOptions($value) {

        return $this->set('cookieOptions', $value);

    }

    /**
     * @detail      Sets or gets the offset between the windows.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function windowsOffset($value) {

        return $this->set('windowsOffset', $value, 'int');

    }

    /**
     * @detail      This event is triggered when the user start to drag any window.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function onDragStart($code) {

        return $this->event('dragStart', $code);

    }

    /**
     * @detail      This event is triggered when the user drop any window.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function onDragEnd($code) {

        return $this->event('dragEnd', $code);

    }

    /**
     * @detail      Moving window to specific position into specific panel. This method have three parameters. The first
     *              one is id of the window we want to move, the second one is number of the panel where we want to move
     *              our window and the last one is the position into this panel.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function move($window, $panel, $position) {

        return $this->method('move');

    }

    /**
     * @detail      Exporting docking's layout into a JSON string.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function exportLayout() {

        return $this->method('exportLayout');

    }

    /**
     * @detail      Importing the docking layout from a JSON string.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function importLayout($layout) {

        return $this->method('importLayout', $layout);

    }

    /**
     * @detail      Setting mode to a specific window. This method accepts two arguments - window id and mode type.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function setWindowMode($window, $mode) {

        return $this->method('setWindowMode', $window, $mode);

    }

    /**
     * @detail      Hiding the close button of a specific window.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function hideCloseButton($window) {

        return $this->method('hideCloseButton', $window);

    }

    /**
     * @detail      Showing the close button of a specific window.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function showCloseButton($window) {

        return $this->method('showCloseButton', $window);

    }

    /**
     * @detail      Hiding the collapse button of a specific window.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function hideCollapseButton($window) {

        return $this->method('hideCollapseButton', $window);

    }

    /**
     * @detail      Showing the collapse button of a specific window.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function showCollapseButton($window) {

        return $this->method('showCollapseButton', $window);

    }

    /**
     * @detail      Expanding a specific window.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function expandWindow($window) {

        return $this->method('expandWindow', $window);

    }

    /**
     * @detail      Collapsing a specific window.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function collapseWindow($window) {

        return $this->method('collapseWindow', $window);

    }

    /**
     * @detail      Moving window in floating mode to a specific position.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function setWindowPosition($window, $x, $y) {

        return $this->method('setWindowPosition', $window, $x, $y);

    }

    /**
     * @detail      Hiding the close buttons of all windows.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function hideAllCloseButtons() {

        return $this->method('hideAllCloseButtons');

    }

    /**
     * @detail      Hiding the collapse buttons of all windows.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function hideAllCollapseButtons() {

        return $this->method('hideAllCollapseButtons');

    }

    /**
     * @detail      Showing the close buttons of all windows.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function showAllCloseButtons() {

        return $this->method('showAllCloseButtons');

    }

    /**
     * @detail      Showing the collapse buttons of all windows.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function showAllCollapseButtons() {

        return $this->method('showAllCollapseButtons');

    }

    /**
     * @detail      Pinning a specific window
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function pinWindow($window) {

        return $this->method('pinWindow', $window);

    }

    /**
     * @detail      Unpinning a specific window
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function unpinWindow($window) {

        return $this->method('unpinWindow', $window);

    }

    /**
     * @detail      Enabling the resize of a specific window which is not docked into a panel.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function enableWindowResize($window) {

        return $this->method('enableWindowResize', $window);

    }

    /**
     * @detail      Disabling the resize of a specific window.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function disableWindowResize($window) {

        return $this->method('disableWindowResize', $window);

    }

    /**
     * @detail      Adding new window to the docking. This method accepts four arguments. The first one is id of the
     *              window we wish to add to the docking. The second argument is window's mode (default, docked,
     *              floating) the third argument is the panel's number and the last one is the position into the panel.
     *              The last three arguments are optional.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function addWindow($window, $mode = null, $panel = null, $position = null) {

        return $this->method('addWindow', $window, $mode, $panel, $position);

    }

    /**
     * @detail      Closing specific window.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function closeWindow($window) {

        return $this->method('closeWindow', $window);

    }

}
