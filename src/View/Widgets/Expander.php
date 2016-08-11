<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Expander widget.
 *
 * @since           1.1
 */
class Expander extends Widget {

    private $content;

    /**
     * @detail      Initialise an Expander widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       mixed $content The initial value of the input.
     *
     * @param       array $params Optional additional parameters
     */
    function __construct($name, $title = null, $content = null, $params = array()) {

        parent::__construct('div', $name, $params);

        if(!substr($name, 0, 1) == '#') {

            if(!$title)
                $title = 'Expander Widget';

            if(!$content)
                $content = 'No content';

            $this->add(new \Hazaar\Html\Div($title));

            $this->add($this->content = new \Hazaar\Html\Div($content));

        }

    }

    /**
     * @detail      Sets or gets the expanding animation duration.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Expander
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
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function collapseAnimationDuration($value) {

        return $this->set('collapseAnimationDuration', $value, 'int');

    }

    /**
     * @detail      Sets or gets expander's state (collapsed or expanded).
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function expanded($value) {

        return $this->set('expanded', $value, 'bool');

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
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function animationType($value) {

        return $this->set('animationType', $value, 'string');

    }

    /**
     * @detail      Sets or gets header's position.
     *
     *              Possible Values:
     *              * 'top'
     *              * 'bottom'
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function headerPosition($value) {

        return $this->set('headerPosition', $value, 'string');

    }

    /**
     * @detail      Sets or gets user interaction used for expanding or collapsing the content.
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
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function toggleMode($value) {

        return $this->set('toggleMode', $value, 'string');

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
     * @return      \\Hazaar\\Widgets\\Expander
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
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function showArrow($value) {

        return $this->set('showArrow', $value, 'bool');

    }

    /**
     * @detail      Callback function called when the item's content needs to be initialized. Useful for initializing
     *              other widgets within the content of jqxExpander.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function initContent($value) {

        return $this->set('initContent', $value);

    }

    /**
     * @detail      This event is triggered when the jqxExpander is going to be expanded.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function onExpanding($code) {

        return $this->event('expanding', $code);

    }

    /**
     * @detail      This event is triggered when the jqxExpander is expanded.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function onExpanded($code) {

        return $this->event('expanded', $code);

    }

    /**
     * @detail      This event is triggered when the jqxExpander is going to be collapsed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function onCollapsing($code) {

        return $this->event('collapsing', $code);

    }

    /**
     * @detail      This event is triggered when the jqxExpander is collapsed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function onCollapsed($code) {

        return $this->event('collapsed', $code);

    }

    /**
     * @detail      Method which is collapsing the expander.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function collapse() {

        return $this->method('collapse');

    }

    /**
     * @detail      Method used for expanding the expander's content.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function expand() {

        return $this->method('expand');

    }

    /**
     * @detail      This method is setting specific content to the expander's header.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function setHeaderContent($content) {

        return $this->method('setHeaderContent', $content);

    }

    /**
     * @detail      This method is setting specific content to the expander.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function setContent($content) {

        return $this->method('setContent', $content);

    }

    /**
     * @detail      Getting expander's content. Returns a string with the content's HTML.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getContent() {

        return $this->method('getContent');

    }

    /**
     * @detail      Getting expander's header content. Returns a string with the header's HTML.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getHeaderContent() {

        return $this->method('getHeaderContent');

    }

    /**
     * @detail      This method is enabling the expander.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function enable() {

        return $this->method('enable');

    }

    /**
     * @detail      This method refreshes the expander.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function invalidate() {

        return $this->method('invalidate');

    }

    /**
     * @detail      This method refreshes the expander.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function refresh() {

        return $this->method('refresh');

    }

    /**
     * @detail      This method renders the expander.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function render() {

        return $this->method('render');

    }

}
