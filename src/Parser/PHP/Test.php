<?php

declare(strict_types=1);

function testFunction(float $text = 1.2, string ...$other): bool
{
    echo $text;

    dump($other);

    return true;
}
