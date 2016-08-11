<?php
/**
 * @file        Hazaar/View/Helper/Geshi.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Helper to convert code into syntactically highlighted code.
 * 
 * @since       2.0.0
 */
class Geshi extends \Hazaar\View\Helper {
    
    private $parser;
    
    public function init($view, $args = array()) {
        
        $this->parser = new \Hazaar\Parser\GeSHi();
        
    }
    
    public function parse($string, $lang = 'php'){
        
        $this->parser->set_source($string);
        
        $this->parser->set_language($lang);
        
        return $this->parser->parse_code();
        
    }
    
}

