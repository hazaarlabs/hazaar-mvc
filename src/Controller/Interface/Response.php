<?php

declare(strict_types=1);

namespace Hazaar\Controller\Interface;

interface Response
{
    public function writeOutput(): void;

    public function getContent(): string;

    public function setContent(mixed $content): void;

    public function addContent(mixed $content): void;
}
