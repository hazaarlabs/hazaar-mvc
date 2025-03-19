<?php

namespace Hazaar\Mail\Mime;

use Hazaar\Util\Arr;

class Message implements \JsonSerializable
{
    protected string $crlf = "\r\n";

    /**
     * @var Part[]
     */
    private array $parts;

    /**
     * @var array<string>
     */
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
                        $part = new Html($part['content'], $part['headers'] ?? []);
                    } else {
                        $part = new Part($part['content'], '', $part['headers'] ?? []);
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
        return $this->headers[$name] ?? '';
    }

    /**
     * @return array<Part>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * @param array<string>|string $contentType
     */
    public function findPart(array|string $contentType): bool|Part
    {
        if (!count($this->parts) > 0) {
            return false;
        }
        $types = is_array($contentType) ? $contentType : [$contentType];
        foreach ($this->parts as $part) {
            if (in_array($part->getContentType(), $types)) {
                return $part;
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
        if (($contentType = $headers['Content-Type'] ?? null) && 'multipart' === substr($contentType, 0, 9)) {
            $contentType_parts = array_map(function ($value) {
                return trim($value, '"');
            }, Arr::unflatten($contentType));
            if ($boundary = $contentType_parts['boundary'] ?? null) {
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
        $headerLines = explode("\n", $content);
        $headers = [];
        $lastHeader = null;
        foreach ($headerLines as $line) {
            if (preg_match('/^\W/', $line)) {
                $headers[$lastHeader] .= "\n ".$line;

                continue;
            }

            if (!preg_match('/^(\S+)\:\s(.*)/', $line, $matches)) {
                continue;
            }
            $headers[$lastHeader = trim($matches[1])] = trim($matches[2]);
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
