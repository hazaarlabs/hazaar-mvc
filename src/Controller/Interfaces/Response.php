<?php

declare(strict_types=1);

namespace Hazaar\Controller\Interfaces;

interface Response
{
    public function __writeOutput(): void;

    public function getContent(): string;

    public function setContent(mixed $content): void;

    public function addContent(mixed $content): void;
}
