<?php

declare(strict_types=1);

namespace Hazaar\HTTP\WebDAV;

/**
 * @implements \ArrayAccess<string, string>
 */
class Property implements \ArrayAccess
{
    private string $namespace;

    /**
     * @var array<string, string>
     */
    private array $attributes = [];

    /**
     * @var array<Property>|string
     */
    private array|string $elements = [];

    public function __construct(null|\DOMElement|\DOMNode $dom = null)
    {
        $this->namespace = $dom->namespaceURI;
        foreach ($dom->childNodes as $node) {
            if (XML_ELEMENT_NODE == $node->nodeType) {
                if ($node->attributes->length > 0) {
                    foreach ($node->attributes as $attr) {
                        $this->attributes[$attr->localName] = $attr->textContent;
                    }
                }
                if (1 == $node->childNodes->length && XML_ELEMENT_NODE !== $node->childNodes->item(0)->nodeType) {
                    $this->elements[$node->localName] = $node->textContent;
                } else {
                    $this->elements[$node->localName] = new Property($node);
                }
            } else {
                $this->elements = $node->textContent;
            }
        }
    }

    public function __toString(): string
    {
        if (!is_array($this->elements)) {
            return $this->elements;
        }

        return '';
    }

    public function __get(string $key): Property
    {
        return $this->get($this->fkey($key));
    }

    public function __set(string $key, mixed $value): void
    {
        if (!$value instanceof Property) {
            $value = new Property($value);
        }
        $this->elements[$key] = $value;
    }

    public function namespaceURI(): string
    {
        return $this->namespace;
    }

    public function get(string $key): Property
    {
        if (array_key_exists($key, $this->elements)) {
            return $this->elements[$key];
        }

        return new Property();
    }

    public function has(string $key): bool
    {
        return array_key_exists($this->fkey($key), $this->elements);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($this->fkey($offset), $this->attributes);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $offset = $this->fkey($offset);
        if (array_key_exists($offset, $this->attributes)) {
            return $this->attributes[$offset];
        }

        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$this->fkey($offset)] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        $offset = $this->fkey($offset);
        if (array_key_exists($offset, $this->attributes)) {
            unset($this->attributes[$offset]);
        }
    }

    private function fkey(string $key): string
    {
        return str_replace('_', '-', $key);
    }
}
