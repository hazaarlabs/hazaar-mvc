<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response;
use Hazaar\Controller\Response\Exception\JSONNotSupported;
use Hazaar\Exception;

/**
 * @implements \ArrayAccess<string, mixed>
 */
class JSON extends Response implements \ArrayAccess
{
    protected mixed $content = [];
    /*
     * If the callback is set, such as in a JSONP request, we use the callback to return
     * the encoded data.
     */
    private ?string $callback = null;

    public function __construct(mixed $data = [], int $status = 200)
    {
        if (!function_exists('json_encode')) {
            throw new JSONNotSupported();
        }
        parent::__construct('application/json', $status);
        $this->content = $data;
    }

    public function __set(string $key, null|int|string $value): void
    {
        $this->set($key, $value);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->content;
    }

    public function &__get(string $key): mixed
    {
        return $this->get($key);
    }

    public function &get(string $key): mixed
    {
        return $this->content[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->content[$key] = $value;
    }

    /**
     * @param array<mixed> $data
     */
    public function populate(array $data): void
    {
        $this->content = $data;
    }

    /**
     * @param array<mixed> $data
     */
    public function push(array $data): void
    {
        $this->content[] = $data;
    }

    // JSONP Tools
    public function setCallback(string $callback): void
    {
        $this->callback = $callback;
    }

    public function getContent(): string
    {
        $data = json_encode($this->content, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === $data) {
            throw new Exception('JSON Encode error: '.json_last_error_msg());
        }
        if (null !== $this->callback) {
            $data = $this->callback."({$data})";
        }

        return $data;
    }

    // ArrayAccess
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->content);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (array_key_exists($offset, $this->content)) {
            return $this->content[$offset];
        }

        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null === $offset) {
            $this->content[] = $value;
        } else {
            $this->content[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->content[$offset]);
    }
}
