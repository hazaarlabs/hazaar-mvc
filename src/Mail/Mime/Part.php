<?php

namespace Hazaar\Mail\Mime;

class Part {

    protected $headers = [];

    protected $content;

    protected $crlf = "\r\n";

    function __construct($content = null, $content_type = 'text/plain', $headers = []) {

        $this->setContent($content);

        if($content_type)
            $this->setContentType($content_type);

        $this->setHeaders($headers);

    }

    public function setHeaders($headers){

        if(!is_array($headers))
            return false;

        foreach($headers as $name => $content)
            $this->setHeader($name, $content);

        return true;

    }

    public function setHeader($header, $content){

        $this->headers[$header] = $content;

    }

    public function getHeader($header){

        return ake($this->headers, $header);

    }

    public function setContentType($type) {

        $this->headers['Content-Type'] = $type . ';';

    }

    public function getContentType() {

        return ake($this->headers, 'Content-Type');

    }

    public function setDescription($text){

        $this->headers['Content-Description'] = $text;

    }

    public function setContent($content) {

        $this->content = $content;

    }

    public function getContent(){

        return $this->content;

    }

    public function detect_break($content, $default = "\r\n"){

        if(($pos = strpos($content, "\n")) == false)
            return $default;

        if($pos > 0 && substr($content, $pos - 1, 1) == "\r")
            return "\r\n";

        return "\n";

    }

    public function encode($width_limit = 76) {

        $message = 'Date: ' . date('r', time()) . $this->crlf;

        foreach($this->headers as $header => $content){

            if(strtolower($header) === 'content-type'){

                $encoding = function_exists('mb_detect_encoding') ? strtolower(mb_detect_encoding($this->content)) : 'utf-8';
            
                $content = trim($content, ' ;') . '; ' . $encoding;

            }
                
            $message .= $header . ': ' . $content . $this->crlf;

        }

        $message .= $this->crlf . (($width_limit > 0) ? wordwrap($this->content, $width_limit, $this->detect_break($this->content), true) : $this->content) . $this->crlf;

        return $message;

    }

    static public function decode($data){

        $pos = strpos($data, "\n\n");

        $headers = \Hazaar\Mail\Mime\Message::parseMessageHeaders(substr($data, 0, $pos));

        $content = substr($data, $pos + 2);

        return new Part($content, null, $headers);
        
    }

}
