<?php

namespace Hazaar\Console;

class Option
{
    public function __construct(
        public string $long,
        public ?string $short = null,
        public ?string $description = null,
        public bool $takesValue = false,
        public mixed $default = null,
        public bool $required = false,
        public ?string $valueType = null
    ) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $this->long)) {
            throw new \InvalidArgumentException("Invalid long option name: {$this->long}");
        }
        if ($this->short && !preg_match('/^[a-zA-Z]$/', $this->short)) {
            throw new \InvalidArgumentException("Invalid short option name: {$this->short}");
        }
    }
}
