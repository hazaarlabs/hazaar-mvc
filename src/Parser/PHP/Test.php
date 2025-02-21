<?php

/**
 * @file Test.php
 *
 * @brief This file contains the Test class.
 *
 * @details This file contains the Test class. This is a test class.
 *
 * @version 1.0
 */
declare(strict_types=1);

namespace Hazaar\Test;

/**
 * This is a test namespace.
 */
const TEST_CONSTANT = 'test';

/**
 * This is a test function.
 *
 * @param float  $precision   The precision of the text
 * @param string $description The description of the text
 */
function testFunction(float $precision = 1.2, string $description = 'none'): bool
{
    echo $precision;

    if ($description) {
        echo $description;
    }

    return true;
}

/**
 * This is a variatic function.
 * 
 * @param string $name The name of the person
 * @param int    $dob  The date of birth
 */
function variaticFunction(string $name, int $dob): void
{
    $args = func_get_args();

    dump($args);
}

/**
 * This is a test interface.
 */
interface TestInterface
{
    /**
     * This is a test method.
     *
     * @param string $text The text to display
     */
    public function testMethod(int $n, string $text = 'Hello World'): void;
}

/**
 * This is a test trait.
 */
trait TestTrait
{
    /**
     * This is a test trait.
     */
    public function testTrait(): void
    {
        echo 'test';
    }
}

/**
 * This is a test class.
 *
 * Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.
 * Sed nisi. Nulla quis sem at nibh elementum imperdiet.
 */
abstract class BaseClass
{
    /**
     * @var array<mixed>
     */
    public static array $names = [
        'one' => 'John',
        'two' => 'Jane',
        'three' => [
            'Jill', 'Jack',
        ],
        'four' => [
            'Jenny', 'James', [1, 2, 3, 4, 5],
        ],
        'five' => [
            'primary' => 'John',
            'secondary' => 'Jane',
        ],
    ];
}

/**
 * This is a test class.
 *
 * @internal
 */
class TestClass extends BaseClass implements TestInterface
{
    public const TEST_CONSTANT = 'test';

    public static int $age = 21;

    public static float $height = 1.8;

    public static bool $active = true;

    public static array $names = [
        'one' => 'John',
        'two' => 'Jane',
        'three' => [
            'Jill', 'Jack',
        ],
        'four' => [
            'Jenny', 'James', [1, 2, 3, 4, 5],
        ],
        'five' => [
            'primary' => 'John',
            'secondary' => 'Jane',
        ],
    ];

    /**
     * This is a test property.
     */
    protected static string $name = 'John Doe';

    /**
     * This is a test method.
     */
    public function testMethod(int $number, ?string $text = 'Hello World'): void
    {
        echo $text;
        if ('test' === $text) {
            echo 'true';
        }
    }

    /**
     * This is also a test method.
     */
    public function testMethod2(string $text = 'Hello World'): ?string
    {
        return $text;
    }
}
