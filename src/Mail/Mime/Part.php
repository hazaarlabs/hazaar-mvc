<?php

namespace Hazaar\Mail\Mime;

class Part implements \JsonSerializable
{
    /** @var array<string> */
    protected array $headers = [];
    protected string $content = '';
    protected string $crlf = "\r\n";

    /**
     * @param array<string> $headers
     */
    public function __construct(string $content = null, string $content_type = null, array $headers = [])
    {
        if ($content) {
            $this->setContent($content);
        }
        if ($content_type) {
            $this->setContentType($content_type);
        }
        $this->setHeaders($headers);
    }

    /**
     * @param array<string> $headers
     */
    public function setHeaders(array $headers): bool
    {
        if (!is_array($headers)) {
            return false;
        }
        foreach ($headers as $name => $content) {
            $this->setHeader($name, $content);
        }

        return true;
    }

    public function setHeader(string $header, string $content): void
    {
        $this->headers[$header] = $content;
    }

    public function getHeader(string $header): string
    {
        return ake($this->headers, $header);
    }

    public function setContentType(string $type): void
    {
        $this->headers['Content-Type'] = $type.';';
    }

    public function getContentType(): string
    {
        return ake($this->headers, 'Content-Type');
    }

    public function setDescription(string $text): void
    {
        $this->headers['Content-Description'] = $text;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function detect_break(string $content, string $default = "\r\n"): string
    {
        $pos = strpos($content, "\n");
        if (false == $pos) {
            return $default;
        }
        // @phpstan-ignore-next-line
        if ($pos > 0 && "\r" == substr($content, $pos - 1, 1)) {
            return "\r\n";
        }

        return "\n";
    }

    public function encode(int $width_limit = 76): string
    {
        $message = 'Date: '.date('r', time()).$this->crlf;
        foreach ($this->headers as $header => $content) {
            if ('content-type' === strtolower($header)) {
                if ($encoding = function_exists('mb_detect_encoding') ? strtolower(mb_detect_encoding($this->content)) : 'utf-8') {
                    $content = trim($content, ' ;').'; charset='.$encoding;
                }
            }
            $message .= $header.': '.$content.$this->crlf;
        }
        $message .= $this->crlf.(($width_limit > 0) ? wordwrap($this->content, $width_limit, $this->detect_break($this->content), true) : $this->content).$this->crlf;

        return $message;
    }

    public static function decode(string $data): Part
    {
        $pos = strpos($data, "\n\n");
        $headers = Message::parseMessageHeaders(substr($data, 0, $pos));
        $content = substr($data, $pos + 2);

        return new Part($content, ake($headers, 'Content-Type'), $headers);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'headers' => $this->headers,
            'content' => $this->content,
        ];
    }
}
