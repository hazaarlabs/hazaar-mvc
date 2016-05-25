<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Basic button widget.
 *
 * @since           1.1
 */
class FileBrowser {

    private $name;

    private $div;

    private $settings;

    private $jquery;

    /**
     * @detail      Initialise a file browser widget
     *
     * @param       string $name The ID of the file browser element to create.
     *
     * @param       string $connect The URL to connect to
     */
    function __construct($name, $connect, $params = array()) {

        $this->div = new \Hazaar\Html\Div(NULL, $params);

        $this->div->id($this->name = $name);

        if(! $connect)
            $connect = new \Hazaar\Application\Url('hazaarBrowser');

        $this->settings = new \Hazaar\Map(array('connect' => (string)$connect));

        $this->jquery = \Hazaar\Html\jQuery::getInstance();

    }

    public function __toString() {

        $args = $this->settings->toJson();

        $this->jquery->exec("$('#" . $this->name . "').fileBrowser($args);");

        return $this->div->renderObject();

    }

    public function set($key, $value) {

        $this->settings[$key] = $value;

        return $this;

    }

    /**
     * @detail      Set the URL to connect to for directory data
     *
     * @param       string $url The URL to connect to.
     *
     * @return      FileBrowser
     */
    public function connect($url = TRUE) {

        return $this->set('connect', (string)$url);

    }

    public function title($title) {

        return $this->set('title', $title);

    }

    /**
     * @detail      Set whether folders should be listed in the detail panel
     *
     * @param       boolean $value True/false value.  Default is true.
     *
     * @return      FileBrowser
     */
    public function listfolders($value = TRUE) {

        return $this->set('listfolders', $value);

    }

    public function width($pixels) {

        return $this->set('width', $pixels);

    }

    public function height($pixels) {

        return $this->set('height', $pixels);

    }

    public function autoexpand($value) {

        return $this->set('autoexpand', $value);

    }

    public function showinfo($value = TRUE) {

        return $this->set('showinfo', $value);

    }

    public function allowmultiple($value = TRUE) {

        return $this->set('allowmultiple', $value);

    }

    public function previewsize($width = NULL, $height = NULL) {

        return $this->set('previewsize', array('w' => $width, 'h' => $height));

    }

    public function useMeta($boolean = TRUE) {

        return $this->set('useMeta', $boolean);

    }

}