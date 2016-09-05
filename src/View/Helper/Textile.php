<?php
/**
 * @file        Hazaar/View/Helper/Textile.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Helper to convert textile markup into HTML
 *
 * @detail      The textile helper uses the classTextile text parser by "Dean Allen":mailto:dean@textism.com to parse
 *              text formatted in textile syntax.
 * 
 *              For details on how to format text using Textile see [[Using Textile]]
 * 
 *              To use the Textile helper, add the helper to your view from your [[Hazaar\Controller\Action|Action Controller]]:
 * 
 *              pre. $this->view->addHelper('textile');
 * 
 *              Then from within your view you can call the textile parser:
 * 
 *              pre. <?=$this->textile->parse($this->mytextilecontent);?>
 *
 * @since       2.0.0
 */
class Textile extends \Hazaar\View\Helper {

    private $parser;

    public function import(){
        
        if(!class_exists('Hazaar\Parser\Textile'))
            throw new \Exception('Textile module not available.  Please install hazaarlabs/hazaar-parser with Composer.');

    }

    public function init($view, $args = array()) {

        $this->parser = new \Hazaar\Parser\Textile();

    }

    public function parse($string) {

        return $this->parser->textileThis($string);

    }

}

