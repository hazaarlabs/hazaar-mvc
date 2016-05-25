<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Splitter button widget.
 *
 * @since           1.1
 */
class Splitter extends Widget {

    /**
     * @detail      Initialise a Splitter widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       string $value The initial value of the input.
     */
    function __construct($name, $value = null) {

        parent::__construct('div', $name);

    }

    public function addPanel($panel, $size = '50%', $min = 0, $collapsible = false, $collapsed = false) {

        $panels = $this->get('panels');

        if(!is_array($panels))
            $panels = array();

        if(count($panels) >= 2) {

            throw new \Exception('jqxPanels only support two DIVs in a single splitter.  To have more, you need to split an existing panel!');

        }

        $panels[] = array(
            'size' => $size,
            'min' => $min,
            'collapsible' => $collapsible,
            'collapsed' => $collapsed
        );

        $this->set('panels', $panels);

        /**
         * Panels need to be wrapped in a DIV to work, so if we are not getting a DIV element, wrap the panel in a DIV.
         * This will also take care of nested panels and string content.
         */
        if(!$panel instanceof \Hazaar\Html\Div) {

            $panel = new \Hazaar\Html\Div($panel);

        }

        return $this->add($panel);

    }

    public function panels() {

        return $this->set('panels', func_get_args());

    }

    /**
     * @detail      Sets or gets the orientation property.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function orientation($value) {

        return $this->set('orientation', $value, 'string');

    }

    /**
     * @detail      Sets or gets the disabled property.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function disabled($value) {

        return $this->set('disabled', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the resizable property. When this property is set to false, the user will not be able to
     *              move the split bar.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function resizable($value) {

        return $this->set('resizable', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the size of the split bar.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function splitBarSize($value) {

        return $this->set('splitBarSize', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether the split bar is displayed.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function showSplitBar($value) {

        return $this->set('showSplitBar', $value, 'bool');

    }

    /**
     * @detail      This event is triggered when the 'resize' operation has ended.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function resize($code) {

        return $this->event('resize', $code);

    }

    /**
     * @detail      This event is triggered when the 'resizeStart' operation has started.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function resizeStart($code) {

        return $this->event('resizeStart', $code);

    }

    /**
     * @detail      This event is triggered when a panel is expanded.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function expanded($code) {

        return $this->event('expanded', $code);

    }

    /**
     * @detail      This event is triggered when a panel is collapsed.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function collapsed($code) {

        return $this->event('collapsed', $code);

    }

    /**
     * @detail      Expands a panel.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      string
     */
    public function expand() {

        return $this->method('expand');

    }

    /**
     * @detail      Collapse a panel.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      string
     */
    public function collapse() {

        return $this->method('collapse');

    }

    /**
     * @detail      Renders the jqxSplitter.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      string
     */
    public function render() {
        return $this->method('render');

    }

    /**
     * @detail      Refreshes the jqxSplitter. This method will perform a layout and will arrange the split panels.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      string
     */
    public function refresh() {

        return $this->method('refresh');

    }

    /**
     * @detail      Enables the jqxSplitter.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      string
     */
    public function enable() {

        return $this->method('enable');

    }

}
