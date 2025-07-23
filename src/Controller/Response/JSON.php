<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response;
use Hazaar\Controller\Response\Exception\JSONNotSupported;

/**
 * @implements \ArrayAccess<string, mixed>
 */
class JSON extends Response implements \ArrayAccess
{
    /**
     * @var array<mixed>
     */
    protected array $jsonContent = [];
    /*
     * If the callback is set, such as in a JSONP request, we use the callback to return
     * the encoded data.
     */
    private ?string $callback = null;

    /**
     * @param array<mixed> $data
     *
     * @throws JSONNotSupported
     */
    public function __construct(array $data = [], int $status = 200)
    {
        if (!function_exists('json_encode')) {
            throw new JSONNotSupported();
        }
        parent::__construct('application/json', $status);
        $this->setContent($data);
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
        return $this->jsonContent;
    }

    public function &__get(string $key): mixed
    {
        return $this->get($key);
    }

    public function &get(string $key): mixed
    {
        return $this->jsonContent[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->jsonContent[$key] = $value;
    }

    /**
     * @param array<mixed> $data
     */
    public function populate(array $data): void
    {
        $this->jsonContent = $data;
    }

    /**
     * @param array<mixed> $data
     */
    public function push(array $data): void
    {
        $this->jsonContent[] = $data;
    }

    // JSONP Tools
    public function setCallback(string $callback): void
    {
        $this->callback = $callback;
    }

    public function setContent(mixed $data): void
    {
        $this->jsonContent = (array) $data;
    }

    public function getContent(): string
    {
        $data = json_encode($this->jsonContent, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === $data) {
            throw new \Exception('JSON Encode error: '.json_last_error_msg());
        }
        if (null !== $this->callback) {
            $data = $this->callback."({$data})";
        }

        return $data;
    }

    // ArrayAccess
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->jsonContent);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (array_key_exists($offset, $this->jsonContent)) {
            return $this->jsonContent[$offset];
        }

        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null === $offset) {
            $this->jsonContent[] = $value;
        } else {
            $this->jsonContent[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->jsonContent[$offset]);
    }
}
