<?php

namespace Hazaar\Mail;

use Hazaar\Mail\Mime\Part;

class Html extends Part
{
    private $boundary;

    private $html;
    private $params;

    public function __construct($html)
    {
        parent::__construct();

        $this->html = $html instanceof Template ? $html : new Template($html);

        $this->boundary = '----alt_border_'.uniqid();

        parent::setContentType('multipart/alternative; boundary="'.$this->boundary.'"');
    }

    public function setParams($params)
    {
        $this->params = $params;
    }

    public function getContentType()
    {
        return 'text/html; charset=UTF-8';
    }

    public function getContent()
    {
        if (null === $this->content) {
            return $this->html->render($this->params);
        }

        return $this->content;
    }

    public function encode($width_limit = 998)
    {
        $html = $this->html->render($this->params);

        $text = new Part(str_replace('<br>', "\r\n", strip_tags($html, '<br>')), 'text/plain');

        $html = new Part($html, self::getContentType());

        $message = '--'.$this->boundary.$this->crlf.$text->encode($width_limit).$this->crlf;

        $message .= '--'.$this->boundary.$this->crlf.$html->encode($width_limit).$this->crlf."--{$this->boundary}--".$this->crlf.$this->crlf;

        $this->setContent($message);

        return parent::encode(0);
    }
}
