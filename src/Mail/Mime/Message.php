<?php

namespace Hazaar\Mail\Mime;

class Message implements \JsonSerializable
{
    protected string $crlf = "\r\n";

    /** @var Part[] */
    private array $parts;

    /** @var array<string> */
    private array $headers = [];
    private string $msgid;
    private string $boundary;

    /**
     * @param array<mixed|Part> $parts
     * @param array<string>     $headers
     */
    public function __construct(array $parts = [], $headers = [])
    {
        foreach ($parts as &$part) {
            if (!$part instanceof Part) {
                if (is_array($part) && array_key_exists('content', $part)) {
                    if (isset($part['type']) && 'html' === $part['type']) {
                        $part = new Html($part['content'], ake($part, 'headers'));
                    } else {
                        $part = new Part($part['content'], '', ake($part, 'headers'));
                    }
                } else {
                    $part = new Part($part['content'], '', $part['headers']);
                }
            }
        }
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

    public function addPart(Part $part): void
    {
        $this->parts[] = $part;
    }

    /**
     * @param array<string>|string $headers
     */
    public function addHeaders(array|string $headers): void
    {
        if (!is_array($headers)) {
            $headers = [$headers];
        }
        $this->headers = array_merge($this->headers, $headers);
    }

    /**
     * @return array<string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): string
    {
        return ake($this->headers, $name, false);
    }

    /**
     * @return array<Part>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * @param array<string>|string $content_type
     */
    public function findPart(array|string $content_type): bool|Part
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

    public function encode(): string
    {
        $message = $this->crlf.'This is a multipart message in MIME format'.$this->crlf.$this->crlf;
        foreach ($this->parts as $part) {
            $message .= '--'.$this->boundary.$this->crlf;
            $message .= $part->encode(998);
        }
        $message .= '--'.$this->boundary.'--'.$this->crlf.$this->crlf;

        return $message;
    }

    public static function decode(string $data): Message
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

    /**
     * @return array<string>
     */
    public static function parseMessageHeaders(string $content): array
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

    public function jsonSerialize(): mixed
    {
        return [
            'headers' => $this->headers,
            'parts' => $this->parts,
        ];
    }
}
