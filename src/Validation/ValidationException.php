<?php

declare(strict_types=1);

namespace Hazaar\Validation;

class ValidationException extends \InvalidArgumentException
{
    /**
     * @param array<string> $errors An array of validation error messages
     */
    public function __construct(array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }
}
