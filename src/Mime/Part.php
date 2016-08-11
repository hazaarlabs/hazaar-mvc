<?php

namespace Hazaar\Mime;

class Part {

    protected $content;

    protected $content_type;

    function __construct($content = null, $content_type = 'text/plain') {

        $this->setContent($content);

        $this->setContentType($content_type);

    }

    public function setContentType($type) {

        $this->content_type = $type;

    }

    public function getContentType() {

        return $this->content_type;

    }

    public function setContent($content) {

        $this->content = $content;

    }

    public function encode($boundary) {

        $message = '--' . $boundary . "\n";

        $message .= 'Date: ' . date('r', time()) . "\n";

        $message .= 'Content-Type: ' . $this->content_type . ";\n";

        $message .= "\n" . $this->content;

        return $message;

    }

}
