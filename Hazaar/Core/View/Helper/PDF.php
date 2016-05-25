<?php
/**
 * @file        Hazaar/View/Helper/PDF.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

class PDF extends \Hazaar\View\Helper {

    public function init($view, $args = array()) {

        $view->requires($this->application->url() . '/hazaar/js/pdfobject.js');

        return '';

    }

    public function render($source, $params = array()) {
        
        return new \Hazaar\Html\Div(new \Hazaar\Html\PDF($source), $params);
        
    }

    public function script($id, $source, $width = null, $height = null) {

        $config = array(
            'url' => $source
        );

        if($id)
            $config['id'] = $id;

        if($width) {

            if(is_numeric($width))
                $width = "{$width}px";

            $config['width'] = $width;

        }

        if($height) {

            if(is_numeric($height))
                $height = "{$height}px";

            $config['height'] = $height;

        }

        $code = "var hazaarPDF = new PDFObject(" . json_encode($config) . ").embed('$id');";

        return $code;

    }

}

