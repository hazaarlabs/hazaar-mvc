<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Window widget class
 *
 * @since           1.1
 */
class Window extends Widget {

    private $title;

    private $content;

    private $buttons;

    /**
     * @detail      Window widget constructor
     *
     * @since       1.1
     *
     * @param       string $name The unique name of the widget
     *
     * @param       mixed $content Any content to add to the window
     *
     * @param       mixed $title Can be either a string or an array with an image and a string in it.
     */
    function __construct($name, $content = null, $title = null) {

        if(is_null($title))
            $title = 'New Window';

        $this->title = new \Hazaar\Html\Div($title);

        $this->content = new \Hazaar\Html\Div($content);

        $this->content->add($this->buttons = new \Hazaar\Html\Div(null, array('style' => 'text-align: right')));

        parent::__construct('div', $name, null, false, array(
            $this->title,
            $this->content
        ));

    }

    /**
     * @detail      Sets or gets whether the window is disabled.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function disabled($value) {

        return $this->set('disabled', $value, 'bool');

    }

    /**
     * @detail      Determines whether the window is collapsed.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function collapsed($value) {

        return $this->set('collapsed', $value, 'bool');

    }

    /**
     * @detail      Determines the duration in milliseconds of the expand/collapse animation.
     *
     * @since       1.1
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function collapseAnimationDuration($value) {

        return $this->set('collapseAnimationDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets window's minimum height.
     *
     * @since       1.1
     *
     * @param       mixed $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function minHeight($value) {

        return $this->set('minHeight', $value);

    }

    /**
     * @detail      Sets or gets window's maximum height.
     *
     * @since       1.1
     *
     * @param       mixed $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function maxHeight($value) {

        return $this->set('maxHeight', $value);

    }

    /**
     * @detail      Sets or gets window's minimum width.
     *
     * @since       1.1
     *
     * @param       mixed $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function minWidth($value) {

        return $this->set('minWidth', $value);

    }

    /**
     * @detail      Sets or gets window's maximum width.
     *
     * @since       1.1
     *
     * @param       mixed $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function maxWidth($value) {

        return $this->set('maxWidth', $value);

    }

    /**
     * @detail      Sets or gets whether a close button will be visible.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function showCloseButton($value) {

        return $this->set('showCloseButton', $value, 'bool');

    }

    /**
     * @detail      Sets or gets whether the collapse button will be visible.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function showCollapseButton($value) {

        return $this->set('showCollapseButton', $value, 'bool');

    }

    /**
     * @detail      Sets or gets whether the window will be shown after it's creation.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function autoOpen($value) {

        return $this->set('autoOpen', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the key which could be used for closing the window when it's on focus. Possible value is
     *              every keycode and the 'esc' strig (for the escape key).
     *
     * @since       1.1
     *
     * @param       mixed $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function keyboardCloseKey($value) {

        return $this->set('keyboardCloseKey', $value);

    }

    /**
     * @detail      Determines whether the keyboard navigation is enabled or disabled.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function keyboardNavigation($value) {

        return $this->set('keyboardNavigation', $value, 'bool');

    }

    /**
     * @detail      Sets or gets window's title content.
     *
     * @since       1.1
     *
     * @param       string $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function title($value) {

        return $this->set('title', $value, 'string');

    }

    /**
     * @detail      Sets or gets window's content's html content.
     *
     * @since       1.1
     *
     * @param       string $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function content($value) {

        return $this->set('content', $value, 'string');

    }

    /**
     * @detail      Initializes the jqxWindow's content.
     *
     * @since       1.1
     *
     * @param       string $code The javascript code to execute to initialise the contents.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function initContent($value) {

        return $this->set('initContent', $value);

    }

    /**
     * @detail      Sets or gets whether the window is draggable.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function draggable($value) {

        return $this->set('draggable', $value, 'bool');

    }

    /**
     * @detail      Enables or disables whether the end-user can resize the window.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function resizable($value) {

        return $this->set('resizable', $value, 'bool');

    }

    /**
     * @detail      Sets or gets window's close and show animation type.
     *
     *              Possible Values:
     *               * 'none'
     *               * 'fade'
     *               * 'slide'
     *               * 'combined'
     *
     * @since       1.1
     *
     * @param       string $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function animationType($value) {

        return $this->set('animationType', $value, 'string');

    }

    /**
     * @detail      Sets or gets window's close animation duration.
     *
     * @since       1.1
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function closeAnimationDuration($value) {

        return $this->set('closeAnimationDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets window's show animation duration.
     *
     * @since       1.1
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function showAnimationDuration($value) {

        return $this->set('showAnimationDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether the window is displayed as a modal dialog. If the jqxWindow's mode is set to
     *              modal, the window blocks user interaction with the underlying user interface.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function isModal($value) {

        return $this->set('isModal', $value, 'bool');

    }

    /**
     * @detail      Sets or gets window's position.
     *
     *              The value could be in the following formats:
     *              * 'center'
     *              * 'top, left'
     *              * array('x' => 300, 'y' => 500 )
     *              * array(300, 500)
     *
     * @since       1.1
     *
     * @param       mixed $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function position($value) {

        return $this->set('position', $value);

    }

    /**
     * @detail      Sets or gets window's close button size.
     *
     * @since       1.1
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function closeButtonSize($value) {

        return $this->set('closeButtonSize', $value, 'int');

    }

    /**
     * @detail      This setting specifies what happens when the user clicks the jqxWindow's close button.
     *
     *              Possible Values:
     *              * 'hide'
     *              * 'close'-clicking the close button removes the window from the DOM
     *
     * @since       1.1
     *
     * @param       string $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function closeButtonAction($value) {

        return $this->set('closeButtonAction', $value, 'string');

    }

    /**
     * @detail      Sets or gets the jqxWindow's background displayed over the underlying user interface when the window
     *              is in modal dialog mode.
     *
     * @since       1.1
     *
     * @param       int $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function modalOpacity($value) {

        return $this->set('modalOpacity', $value, 'int');

    }

    /**
     * @detail      Sets or gets the screen area which is available for dragging(moving) the jqxWindow.
     *
     *              Example value:
     *              * array('left' => 300, 'top' => 300, 'width' => 600, 'height' => 600)
     *
     *              By default, the dragArea is null which means that the users will be able to drag the window in the
     *              document's body bounds.
     *
     * @since       1.1
     *
     * @param       array $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function dragArea($value) {

        return $this->set('dragArea', $value);

    }

    /**
     * @detail      Sets or gets submit button. When a ok/submit button is specified you can use this button to interact
     *              with the user. When any user presses the submit button window is going to be closed and the dialog
     *              result will be in the following format: { OK: true, Cancel: false, None: false }.
     *
     * @since       1.1
     *
     * @param       mixed $value This can be either a button widget to add to the content, or the name of an existing
     *              button that is in the content already.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function okButton($value) {

        if($value instanceof Widget) {

            $this->buttons->add($value->element);

            $id = $value->attr('id');

        } else {

            $id = (string)$value;

        }

        $value = "!$('#$id')";

        return $this->set('okButton', $value);

    }

    /**
     * @detail      Sets or gets cancel button. When a cancel button is specified you can use this button to interact
     *              with the user. When any user press the cacel button window is going to be closed and the dialog
     *              result will be in the following format: { OK: false, Cancel: true, None: false }.
     *
     * @since       1.1
     *
     * @param       mixed $value This can be either a button widget to add to the content, or the name of an existing
     *              button that is in the content already.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function cancelButton($value) {

        if($value instanceof Widget) {

            $this->buttons->add($value->element);

            $id = $value->attr('id');

        } else {

            $id = (string)$value;

        }

        $value = "!$('#$id')";

        return $this->set('cancelButton', $value);

    }

    /**
     * @detail      This event is triggered when the user create new window.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onCreated($code) {

        return $this->event('created', $code);

    }

    /**
     * @detail      This event is triggered when the window is dragging by the user.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onMoving($code) {

        return $this->event('moving', $code);

    }

    /**
     * @detail      This event is triggered when the window is dropped by the user.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onMoved($code) {

        return $this->event('moved', $code);

    }

    /**
     * @detail      This event is triggered when the window is displayed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onOpen($code) {

        return $this->event('disopenabled', $code);

    }

    /**
     * @detail      This event is triggered when the window is closed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onClose($code) {

        return $this->event('close', $code);

    }

    /**
     * @detail      This event is triggered when the window is expanded.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onExpand($code) {

        return $this->event('expand', $code);

    }

    /**
     * @detail      This event is triggered when the window is collapsed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onCollapse($code) {

        return $this->event('collapse', $code);

    }

    /**
     * @detail      This event is triggered when the end-user is resizing the window.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onResizing($code) {

        return $this->event('resizing', $code);

    }

    /**
     * @detail      This event is triggered when the end-user has resized the window.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onResized($code) {

        return $this->event('resized', $code);

    }

    /**
     * @detail      Closing all open windows which are not modal.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function closeAll() {

        return $this->method('closeAll');

    }

    /**
     * @detail      Setting window's title
     *
     * @since       1.1
     *
     * @param       string $value The title to set
     *
     * @return      string
     */
    public function setTitle($value) {

        return $this->method('setTitle', $value);

    }

    /**
     * @detail      Setting window's content.
     *
     * @since       1.1
     *
     * @param       string $value The content to set
     *
     * @return      string
     */
    public function setContent($content) {

        return $this->method('setContent', $content);

    }

    /**
     * @detail      Enabling the window
     *
     * @since       1.1
     *
     * @return      string
     */
    public function enable() {

        return $this->method('enable');

    }

    /**
     * @detail      Returns true when jqxWindow is opened and false when the jqxWindow is closed.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function isOpen() {

        return $this->method('isOpen');

    }

    /**
     * @detail      Bringing the window to the front.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function bringToFront() {

        return $this->method('disabled');

    }

    /**
     * @detail      Hiding/closing the current window (the action - hide or close depends on the closeButtonAction).
     *
     * @since       1.1
     *
     * @return      string
     */
    public function close() {

        return $this->method('close');

    }

    /**
     * @detail      Opening/showing the current window.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function open() {

        return $this->method('open');

    }

    /**
     * @detail      Expand the current window.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function expand() {

        return $this->method('expand');

    }

    /**
     * @detail      Collapse the current window.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function collapse() {

        return $this->method('collapse');

    }

    /**
     * @detail      Moving the current window.
     *
     * @since       1.1
     *
     * @param       int $top The top position of the window
     *
     * @param       int $left The left position of the window
     *
     * @return      string
     */
    public function move($top, $left) {

        return $this->method('move', $top, $left);

    }

    /**
     * @detail      Resizes the window. The 'resizable' property is expected to be set to "true".
     *
     * @since       1.1
     *
     * @param       int $width The new width of the window
     *
     * @param       int $height The new height of the window
     *
     * @return      string
     */
    public function resize($width, $height) {

        return $this->method('resize', $width, $height);

    }

}
