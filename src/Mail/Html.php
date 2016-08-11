<?php

namespace Hazaar\Mail;

class Html extends \Hazaar\Mime\Part {

    private $boundary;

    private $html;

    function __construct($html) {

        parent::__construct();

        $this->html = $html;

        $this->boundary = '----alt_border_' . uniqid();

        parent::setContentType('multipart/alternative; boundary="' . $this->boundary . '"');

    }

    public function encode($boundary) {

        $text = new \Hazaar\Mime\Part('Your browser does not support HTML email!', 'text/plain');

        $html = new \Hazaar\Mime\Part($this->html, 'text/html');

        $message = $text->encode($this->boundary) . "\n\n";

        $message .= $html->encode($this->boundary);

        $message .= "\n\n--" . $this->boundary . '--';

        $this->setContent($message);

        return parent::encode($boundary);

    }

}
