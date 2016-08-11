<?php

namespace Hazaar\Mime;

class Message {

    private $parts = array();

    private $headers = array();

    private $msgid = null;

    private $boundary;

    function __construct($parts = array()) {

        $this->parts = $parts;

        $this->msgid = uniqid();

        $this->boundary = '----msg_border_' . $this->msgid;

        $this->addHeaders(array(

            'MIME-Version' => '1.0',

            'Content-Type' => 'multipart/mixed; boundary="' . $this->boundary . '"'
        ));

    }

    public function addPart(Part $part) {

        $this->parts[] = $part;

    }

    public function addHeaders($headers) {

        if(!is_array($headers))
            $headers = array($headers);

        $this->headers = array_merge($this->headers, $headers);

    }

    public function getHeaders() {

        return $this->headers;

    }

    public function encode() {

        $message = "\nThis is a multipart message in MIME format\n\n";

        foreach($this->parts as $part) {

            $message .= $part->encode($this->boundary) . "\n\n";

            $message .= "--" . $this->boundary . "--\n";

        }

        return $message;

    }

}
