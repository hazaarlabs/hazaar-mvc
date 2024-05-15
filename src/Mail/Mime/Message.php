<?php

namespace Hazaar\Mail\Mime;

use Hazaar\Mail\Attachment;
use Hazaar\Mail\Html;

class Message
{
    protected $crlf = "\r\n";

    private $parts = [];

    private $headers = [];

    private $msgid;

    private $boundary;

    private $params;

    public function __construct($parts = [], $headers = [])
    {
        $this->parts = $parts;

        $this->msgid = uniqid();

        $this->boundary = '----msg_border_'.$this->msgid;

        $this->addHeaders($headers);

        $this->addHeaders([
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/mixed; boundary="'.$this->boundary.'"',
        ]);
    }

    public function __toString()
    {
        return $this->encode();
    }

    public function setParams($params)
    {
        foreach ($this->parts as $part) {
            if ($part instanceof Html) {
                $part->setParams($params);
            }
        }
    }

    public function addPart(Part $part)
    {
        if ($part instanceof Attachment) {
            $part->setBase64Encode(true);
        }

        $this->parts[] = $part;
    }

    public function addParts(array $parts)
    {
        foreach ($parts as $part) {
            $this->addPart($part);
        }

        return $this;
    }

    public function addHeaders($headers)
    {
        if (!is_array($headers)) {
            $headers = [$headers];
        }

        $this->headers = array_merge($this->headers, $headers);
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getHeader($name)
    {
        return ake($this->headers, $name, false);
    }

    public function getParts()
    {
        return $this->parts;
    }

    public function findPart($content_type)
    {
        if (is_array($this->parts)) {
            $types = is_array($content_type) ? $content_type : [$content_type];

            foreach ($this->parts as $part) {
                if (in_array($part->getContentType(), $types)) {
                    return $part;
                }
            }
        }

        return false;
    }

    public function encode()
    {
        $message = $this->crlf.'This is a multipart message in MIME format'.$this->crlf.$this->crlf;

        foreach ($this->parts as $part) {
            $message .= '--'.$this->boundary.$this->crlf;

            $message .= $part->encode(998);
        }

        $message .= '--'.$this->boundary.'--'.$this->crlf.$this->crlf;

        return $message;
    }

    public static function decode($data)
    {
        $pos = strpos($data, "\n\n");

        $headers = Message::parseMessageHeaders(substr($data, 0, $pos));

        $content = substr($data, $pos + 2);

        if (($content_type = ake($headers, 'Content-Type')) && 'multipart' === substr($content_type, 0, 9)) {
            $content_type_parts = array_map(function ($value) {
                return trim($value, '"');
            }, array_unflatten($content_type));

            if ($boundary = ake($content_type_parts, 'boundary')) {
                $parts = explode('--'.$boundary."\n", $content);

                array_shift($parts);

                $content = [];

                foreach ($parts as $part) {
                    $content[] = Part::decode($part);
                }
            }
        }

        return new Message($content, $headers);
    }

    public static function parseMessageHeaders($content)
    {
        $header_lines = explode("\n", $content);

        $headers = [];

        $last_header = null;

        foreach ($header_lines as $line) {
            if (preg_match('/^\W/', $line)) {
                $headers[$last_header] .= "\n ".$line;

                continue;
            }

            if (!preg_match('/^(\S+)\:\s(.*)/', $line, $matches)) {
                continue;
            }

            $headers[$last_header = trim($matches[1])] = trim($matches[2]);
        }

        return $headers;
    }
}
