<?php

namespace Hazaar\Mail\Mime;

use Hazaar\Mail\Template;

class Html extends Part implements \JsonSerializable
{
    private string $boundary;
    private Template $html;

    /** @var array<mixed> */
    private array $params = [];

    /**
     * @param array<string> $headers
     */
    public function __construct(string|Template $html, array $headers = [])
    {
        parent::__construct('', 'text/html', $headers);
        $this->html = $html instanceof Template ? $html : new Template($html);
        $this->boundary = '----alt_border_'.uniqid();
        parent::setContentType('multipart/alternative; boundary="'.$this->boundary.'"');
    }

    public function __toString(): string
    {
        return $this->encode();
    }

    /**
     * Set the parameters for the template.
     *
     * @param array<mixed> $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function encode(int $width_limit = 998): string
    {
        $html = $this->html->render($this->params);
        $text = new Part(str_replace('<br>', "\r\n", strip_tags($html, '<br>')), 'text/plain');
        $html = new Part($html, 'text/html');
        $message = '--'.$this->boundary.$this->crlf.$text->encode($width_limit).$this->crlf;
        $message .= '--'.$this->boundary.$this->crlf.$html->encode($width_limit).$this->crlf."--{$this->boundary}--".$this->crlf.$this->crlf;
        $this->setContent($message);

        return parent::encode(0);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'type' => 'html',
            'headers' => $this->headers,
            'content' => $this->html->render($this->params),
        ];
    }
}
