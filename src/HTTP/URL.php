<?php

declare(strict_types=1);

namespace Hazaar\HTTP;

/**
 * @implements \ArrayAccess<string, int|string>
 */
class URL implements \ArrayAccess
{
    /**
     * @var array<string, int>
     */
    public array $common_ports = [
        'ftp' => 21,
        'http' => 80,
        'https' => 443,
    ];

    /**
     * @var array<string, int|string>
     */
    private null|array|false|int|string $parts = [
        'scheme' => 'http',
        'host' => 'localhost',
        'path' => '/',
    ];

    /**
     * @var array<string, int|string>
     */
    private array $params = [];

    public function __construct(?string $url = null)
    {
        if (null === $url) {
            return;
        }
        if (false == strpos($url, ':')) {
            $url = 'http://'.$url;
        }
        $this->parts = parse_url($url);
        if (!is_array($this->parts)) {
            return;
        }
        if (array_key_exists('query', $this->parts)) {
            parse_str($this->parts['query'], $this->params);
        }
    }

    public function __get(string $key): int|string
    {
        return $this->get($key);
    }

    public function __set(string $key, int|string $value): void
    {
        $this->set($key, $value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function scheme(?string $value = null): string
    {
        if (null === $value) {
            return $this->parts['scheme'] ?? 'http';
        }

        return $this->parts['scheme'] = $value;
    }

    public function host(?string $value = null): string
    {
        if (null === $value) {
            return $this->parts['host'] ?? '';
        }

        return $this->parts['host'] = $value;
    }

    public function port(?int $value = null): int
    {
        if (null === $value) {
            if (!array_key_exists('port', $this->parts)) {
                $scheme = $this->scheme();
                /*
                 * Check the scheme is a common one and get the port that way if we can.
                 * Doing this first instead of using the services lookup will be faster for these common protocols.
                 */
                $this->parts['port'] = $this->lookupPort($scheme);
            }

            return $this->parts['port'] ?? 0;
        }

        return $this->parts['port'] = (int) $value;
    }

    public function lookupPort(string $scheme): ?int
    {
        if ($port = ($this->common_ports[$scheme] ?? null)) {
            return $port;
        }
        $services_file = DIRECTORY_SEPARATOR.'etc'.DIRECTORY_SEPARATOR.'services';
        if (file_exists($services_file)) {
            $services = file_get_contents($services_file);
            if (preg_match('/^'.$scheme.'\s*(\d*)\/tcp/m', $services, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    public function user(): string
    {
        if (0 == func_num_args()) {
            return $this->parts['user'] ?? '';
        }

        return $this->parts['user'] = func_get_arg(0);
    }

    public function pass(): string
    {
        if (0 == func_num_args()) {
            return $this->parts['pass'] ?? '';
        }

        return $this->parts['pass'] = func_get_arg(0);
    }

    public function path(): string
    {
        if (0 == func_num_args()) {
            return $this->parts['path'] ?? '/';
        }

        return $this->parts['path'] = func_get_arg(0);
    }

    /**
     * @return array<string, int|string>
     */
    public function params(): array
    {
        if (0 == func_num_args()) {
            return $this->parts['query'] ?? [];
        }

        return $this->parts['query'] = func_get_arg(0);
    }

    public function hash(): string
    {
        if (0 == func_num_args()) {
            return $this->parts['fragment'] ?? '';
        }

        return $this->parts['fragment'] = func_get_arg(0);
    }

    public function get(string $key): int|string
    {
        return $this->params[$key] ?? '';
    }

    public function set(string $key, int|string $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * @param array<string, int|string> $array
     */
    public function setParams(array $array): void
    {
        $this->params = array_merge($this->params, $array);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->params);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function offsetUnSet(mixed $key): void
    {
        unset($this->params[$key]);
    }

    public function isSecure(): bool
    {
        return 's' == substr($this->scheme(), -1);
    }

    public function toString(): string
    {
        $scheme = $this->scheme();
        $port = $this->port();

        return $scheme.'://'.(($this->parts['user'] ?? false) ? $this->parts['user'].(($this->parts['pass'] ?? false) ? ':'.$this->parts['pass'] : null).'@' : null)
            .$this->host()
            .(($port === $this->lookupPort($scheme)) ? null : ':'.$port)
            .$this->path()
            .((count($this->params) > 0) ? '?'.http_build_query($this->params) : null);
    }

    public function queryString(): ?string
    {
        if (count($this->params) > 0) {
            return '?'.http_build_query($this->params);
        }

        return null;
    }
}
