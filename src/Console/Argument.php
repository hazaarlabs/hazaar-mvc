<?php

namespace Hazaar\Console;

class Argument
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public bool $required = false
    ) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $this->name)) {
            throw new \InvalidArgumentException("Invalid argument name: {$this->name}");
        }
    }
}
