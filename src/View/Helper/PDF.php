<?php
/**
 * @file        Hazaar/View/Helper/PDF.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\View\Helper;

class PDF extends \Hazaar\View\Helper {

    public function init(\Hazaar\View\Layout $view, $args = []) {

        $view->requires($this->application->url() . '/hazaar/js/pdfobject.js');

        return '';

    }

    public function render($source, $params = []) {

        return new \Hazaar\Html\Div(new \Hazaar\Html\PDF($source), $params);

    }

    public function script($id, $source, $width = null, $height = null) {

        $config = [
            'url' => $source
        ];

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

