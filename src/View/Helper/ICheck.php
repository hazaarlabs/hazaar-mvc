<?php
/**
 * @file        Hazaar/View/Helper/iCheck.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       iCheck view helper
 *
 * @detail      For more information on iCheck, see: http://icheck.fronteed.com/
 *
 * @since       3.0.0
 */
class iCheck extends \Hazaar\View\Helper {

    private $theme = 'minimal';

    private $colour = 'purple';

    public function import() {

        $this->requires('html');

        $this->requires('JQuery');

    }

    /**
     * @detail      Initialise the view helper and include the buttons.css file.  Adds a requirement for the HTML view
     *              helper.
     */
    public function init(\Hazaar\View\Layout $view, $args = array()) {

        $this->cdnjs->load('iCheck', array('skins/all.css', 'icheck.min.js'));

        $this->theme = ake($args, 'theme', 'minimal');

        $this->colour = ake($args, 'colour', 'purple');

    }

    public function post(){

        return $this->html->script("$('input').iCheck({
            checkboxClass: 'icheckbox_{$this->theme}-{$this->colour}',
            radioClass: 'iradio_{$this->theme}-{$this->colour}'
        });");

    }

}