<?php

declare(strict_types=1);

namespace Hazaar\Validation;

class Assert
{
    private mixed $value;

    /**
     * @var array<string>
     */
    private array $errors = [];

    private bool $lazy = false;

    private function __construct(mixed $value = null, bool $lazy = false)
    {
        $this->value = $value;
        $this->lazy = $lazy;
    }

    public static function that(mixed $value): self
    {
        return new self($value);
    }

    public function lazy(): self
    {
        $this->lazy = true;

        return $this;
    }

    public function notEmpty(string $message = 'Value is empty'): self
    {
        if (empty($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    public function string(string $message = 'Value is not a string'): self
    {
        if (!is_string($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    public function integer(string $message = 'Value is not an integer'): self
    {
        if (!is_int($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    public function float(string $message = 'Value is not a float'): self
    {
        if (!is_float($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    public function boolean(string $message = 'Value is not a boolean'): self
    {
        if (!is_bool($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    public function numeric(string $message = 'Value is not numeric'): self
    {
        if (!is_numeric($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    public function email(string $message = 'Value is not a valid email address'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            $this->except($message);
        }

        return $this;
    }

    public function url(string $message = 'Value is not a valid URL'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_URL)) {
            $this->except($message);
        }

        return $this;
    }

    public function ip(string $message = 'Value is not a valid IP address'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_IP)) {
            $this->except($message);
        }

        return $this;
    }

    public function ipv4(string $message = 'Value is not a valid IPv4 address'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->except($message);
        }

        return $this;
    }

    public function ipv6(string $message = 'Value is not a valid IPv6 address'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->except($message);
        }

        return $this;
    }

    public function min(int $min, string $message = 'Value is less than minimum value'): self
    {
        if ($this->value < $min) {
            $this->except($message);
        }

        return $this;
    }

    public function max(int $max, string $message = 'Value is greater than maximum value'): self
    {
        if ($this->value > $max) {
            $this->except($message);
        }

        return $this;
    }

    public function maxLength(int $max, string $message = 'Value length is greater than maximum length'): self
    {
        if (strlen($this->value) > $max) {
            $this->except($message);
        }

        return $this;
    }

    public function minLength(int $min, string $message = 'Value length is less than minimum length'): self
    {
        if (strlen($this->value) < $min) {
            $this->except($message);
        }

        return $this;
    }

    public function matchesRegex(string $pattern, string $message = 'Value does not match pattern'): self
    {
        if (!preg_match($pattern, $this->value)) {
            $this->except($message);
        }

        return $this;
    }

    public function between(int $min, int $max, string $message = 'Value is not between minimum and maximum values'): self
    {
        if ($this->value < $min || $this->value > $max) {
            $this->except($message);
        }

        return $this;
    }

    public function array(string $message = 'Value is not an array'): self
    {
        if (!is_array($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * @param array<mixed> $values
     */
    public function in(array $values, string $message = 'Value is not in the list of allowed values'): self
    {
        if (!in_array($this->value, $values)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * @param array<mixed> $values
     */
    public function notIn(array $values, string $message = 'Value is in the list of disallowed values'): self
    {
        if (in_array($this->value, $values)) {
            $this->except($message);
        }

        return $this;
    }

    public function object(string $message = 'Value is not an object'): self
    {
        if (!is_object($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    public function verify(): bool
    {
        if (!empty($this->errors)) {
            throw new \InvalidArgumentException(implode(', ', $this->errors));
        }

        return true;
    }

    private function except(string $message): void
    {
        if (false === $this->lazy) {
            throw new \InvalidArgumentException($message);
        }
        $this->errors[] = $message;
    }
}
