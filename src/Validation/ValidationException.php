<?php

declare(strict_types=1);

namespace Hazaar\Validation;

/**
 * Exception thrown when validation fails in lazy validation mode.
 *
 * This exception combines multiple validation error messages into a single
 * exception message by joining them with newlines.
 */
class ValidationException extends \InvalidArgumentException
{
    /**
     * Creates a new ValidationException instance.
     *
     * @param array<string> $errors An array of validation error messages
     *                              that occurred during lazy validation
     */
    public function __construct(array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }
}
