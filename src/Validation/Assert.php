<?php

declare(strict_types=1);

namespace Hazaar\Validation;

/**
 * Assert provides a fluent interface for validating values against various criteria.
 * 
 * This class implements a builder pattern for constructing validation chains, allowing
 * you to validate values against multiple criteria in a readable and maintainable way.
 * 
 * The class supports two validation modes:
 * - Immediate mode (default): Throws exceptions as soon as a validation fails
 * - Lazy mode: Collects all validation errors before throwing an exception
 * 
 * Example usage:
 * ```php
 * // Immediate validation mode
 * Assert::that($email)->string()->email();
 * 
 * // Lazy validation mode
 * Assert::that($user)
 *     ->lazy()
 *     ->notEmpty()
 *     ->object()
 *     ->verify();
 * 
 * // Numeric validation
 * Assert::that($age)
 *     ->integer()
 *     ->between(18, 100);
 * ```
 * 
 * @see ValidationException For the exception thrown when validation fails in lazy mode
 */
class Assert
{
    private mixed $value;

    /**
     * @var array<string>
     */
    private array $errors = [];

    private bool $lazy = false;

    /**
     * Creates a new Assert instance with the given value to validate.
     * 
     * @param mixed $value The value to validate
     * @param bool $lazy Whether to use lazy validation
     */
    private function __construct(mixed $value = null, bool $lazy = false)
    {
        $this->value = $value;
        $this->lazy = $lazy;
    }

    /**
     * Static factory method to create a new Assert instance for validating a value.
     * This is the main entry point for starting a validation chain.
     * 
     * @param mixed $value The value to validate
     */
    public static function that(mixed $value): self
    {
        return new self($value);
    }

    /**
     * Enables lazy validation mode where validation errors are collected rather than
     * throwing exceptions immediately. All errors can be checked at once using verify().
     */
    public function lazy(): self
    {
        $this->lazy = true;

        return $this;
    }

    /**
     * Validates that the value is not empty. Empty values include '', null, [], 0, '0', and false.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function notEmpty(string $message = 'Value is empty'): self
    {
        if (empty($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is a string type.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function string(string $message = 'Value is not a string'): self
    {
        if (!is_string($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is an integer type.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function integer(string $message = 'Value is not an integer'): self
    {
        if (!is_int($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is a float type.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function float(string $message = 'Value is not a float'): self
    {
        if (!is_float($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is a boolean type.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function boolean(string $message = 'Value is not a boolean'): self
    {
        if (!is_bool($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is numeric (can be both string numerics and actual numbers).
     * 
     * @param string $message Custom error message when validation fails
     */
    public function numeric(string $message = 'Value is not numeric'): self
    {
        if (!is_numeric($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is not numeric (neither string numerics nor actual numbers).
     * 
     * @param string $message Custom error message when validation fails
     */
    public function notNumeric(string $message = 'Value is numeric'): self
    {
        if (is_numeric($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is a scalar type (integer, float, string or boolean).
     * 
     * @param string $message Custom error message when validation fails
     */
    public function scalar(string $message = 'Value is not a scalar'): self
    {
        if (!is_scalar($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is a valid email address using PHP's filter_var function.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function email(string $message = 'Value is not a valid email address'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is a valid URL using PHP's filter_var function.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function url(string $message = 'Value is not a valid URL'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_URL)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is a valid IP address (IPv4 or IPv6).
     * 
     * @param string $message Custom error message when validation fails
     */
    public function ip(string $message = 'Value is not a valid IP address'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_IP)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is a valid IPv4 address.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function ipv4(string $message = 'Value is not a valid IPv4 address'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is a valid IPv6 address.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function ipv6(string $message = 'Value is not a valid IPv6 address'): self
    {
        if (!filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the numeric value is greater than or equal to the specified minimum.
     * 
     * @param int $min The minimum allowed value
     * @param string $message Custom error message when validation fails
     */
    public function min(int $min, string $message = 'Value is less than minimum value'): self
    {
        if ($this->value < $min) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the numeric value is less than or equal to the specified maximum.
     * 
     * @param int $max The maximum allowed value
     * @param string $message Custom error message when validation fails
     */
    public function max(int $max, string $message = 'Value is greater than maximum value'): self
    {
        if ($this->value > $max) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the string length is not greater than the specified maximum.
     * 
     * @param int $max The maximum allowed length
     * @param string $message Custom error message when validation fails
     */
    public function maxLength(int $max, string $message = 'Value length is greater than maximum length'): self
    {
        if (strlen($this->value) > $max) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the string length is not less than the specified minimum.
     * 
     * @param int $min The minimum allowed length
     * @param string $message Custom error message when validation fails
     */
    public function minLength(int $min, string $message = 'Value length is less than minimum length'): self
    {
        if (strlen($this->value) < $min) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value matches the given regular expression pattern.
     * 
     * @param string $pattern The regular expression pattern to match against
     * @param string $message Custom error message when validation fails
     */
    public function matchesRegex(string $pattern, string $message = 'Value does not match pattern'): self
    {
        if (!preg_match($pattern, $this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the numeric value falls within the specified range (inclusive).
     * 
     * @param int $min The minimum allowed value
     * @param int $max The maximum allowed value
     * @param string $message Custom error message when validation fails
     */
    public function between(int $min, int $max, string $message = 'Value is not between minimum and maximum values'): self
    {
        if ($this->value < $min || $this->value > $max) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is an array.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function array(string $message = 'Value is not an array'): self
    {
        if (!is_array($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value exists in the given array of allowed values.
     * 
     * @param array<mixed> $values Array of allowed values
     * @param string $message Custom error message when validation fails
     */
    public function in(array $values, string $message = 'Value is not in the list of allowed values'): self
    {
        if (!in_array($this->value, $values)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value does not exist in the given array of disallowed values.
     * 
     * @param array<mixed> $values Array of disallowed values
     * @param string $message Custom error message when validation fails
     */
    public function notIn(array $values, string $message = 'Value is in the list of disallowed values'): self
    {
        if (in_array($this->value, $values)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Validates that the value is an object.
     * 
     * @param string $message Custom error message when validation fails
     */
    public function object(string $message = 'Value is not an object'): self
    {
        if (!is_object($this->value)) {
            $this->except($message);
        }

        return $this;
    }

    /**
     * Verifies all validations and throws a ValidationException if any errors occurred
     * during lazy validation. If not in lazy mode, this method will always return true
     * as exceptions are thrown immediately upon validation failure.
     * 
     * @throws ValidationException When validation errors exist in lazy mode
     * @return bool Always returns true if no exceptions are thrown
     */
    public function verify(): bool
    {
        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return true;
    }

    /**
     * Internal method to handle validation failures. Either throws an exception immediately
     * or collects the error message for lazy validation.
     * 
     * @param string $message The error message to handle
     */
    private function except(string $message): void
    {
        if (false === $this->lazy) {
            throw new \InvalidArgumentException($message);
        }
        $this->errors[] = $message;
    }
}
