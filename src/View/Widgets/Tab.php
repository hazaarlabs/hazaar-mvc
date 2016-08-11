<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Tabs widget.
 *
 * @since           1.1
 */
class Tab extends \Hazaar\Html\Div {

    public $label;

    public $source;

    /**
     * @detail      Creates a new tab item for use in a Tabs widget
     *
     * @since       1.1
     *
     * @param       string $label The label for the tab
     * 
     * @param       mixed $content Any content to display inside the tab.  Can be text or HTML objects.
     * 
     * @param       array $params Any additional parameters to set on the tabs
     *
     * @return      \\Hazaar\\Widgets\\Tab
     */
    function __construct($label, $content = null, $params = array()) {

        if(!$label)
            $label = 'Tab';

        $this->label = new \Hazaar\Html\Block('li', $label);

        parent::__construct($content, $params);

    }

    /**
     * @detail      Set whether the tab has a close button or not.
     *
     * @since       1.1
     *
     * @param       string $url The source URL.
     *
     * @return      \\Hazaar\\Widgets\\Tab
     */
    public function hasCloseButton($value = true) {

        $this->label->set('hasclosebutton', strbool($value));

        return $this;

    }

    /**
     * @detail      Set whether the tab can be selected.
     *
     * @since       1.1
     *
     * @param       string $url The source URL.
     *
     * @return      \\Hazaar\\Widgets\\Tab
     */
    public function canSelect($value) {

        $this->label->set('canselect', strbool($value));

        return $this;

    }

    /**
     * @detail      Set the source URL for tab content
     *
     * @since       1.1
     *
     * @param       string $url The source URL.
     *
     * @return      \\Hazaar\\Widgets\\Tab
     */
    public function source($url) {

        $this->set('data-source', $this->source = $url);

        return $this;

    }

}
