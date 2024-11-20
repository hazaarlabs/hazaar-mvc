<?php

declare(strict_types=1);

/**
 * This is a test namespace.
 */

namespace Hazaar\Test;

/**
 * This is a test function.
 *
 * @param float  $text        The text to display
 * @param string $description The description of the text
 */
function testFunction(float $text = 1.2, string $description = 'none'): bool
{
    echo $text;

    dump($description);

    return true;
}

/**
 * @param string $name The name of the person
 * @param int    $dob  The date of birth
 */
function variaticFunction(string $name, int $dob): void
{
    $args = func_get_args();

    dump($args);
}

class TestClass
{
    private string $name;

    public function testMethod(string $text = 'Hello World'): void
    {
        echo $text;
    }
}
