<?php

namespace Hazaar\Mail\Mime;

class Message {

    private $parts = [];

    private $headers = [];

    private $msgid = null;

    private $boundary;

    protected $crlf = "\r\n";

    function __construct($parts = []) {

        $this->parts = $parts;

        $this->msgid = uniqid();

        $this->boundary = '----msg_border_' . $this->msgid;

        $this->addHeaders([
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/mixed; boundary="' . $this->boundary . '"'
        ]);

    }

    public function addPart(Part $part) {

        $this->parts[] = $part;

    }

    public function addHeaders($headers) {

        if(!is_array($headers))
            $headers = [$headers];

        $this->headers = array_merge($this->headers, $headers);

    }

    public function getHeaders() {

        return $this->headers;

    }

    public function encode($params = null) {

        $message = $this->crlf . "This is a multipart message in MIME format" . $this->crlf . $this->crlf;

        foreach($this->parts as $part) {

            $message .= '--' . $this->boundary . $this->crlf;

            $message .= $part->encode(998, $params);

        }

        $message .= "--" . $this->boundary . "--" . $this->crlf . $this->crlf;

        return $message;

    }

}
