<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Validation\Assert;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ValidationTest extends TestCase
{
    public function testAssert(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Assert::that(null)->notEmpty();
    }

    public function testAssertLazy(): void
    {
        $assert = Assert::that(null)->lazy()->notEmpty();
        $this->assertInstanceOf(Assert::class, $assert);
        $this->expectException(\InvalidArgumentException::class);
        $assert->verify();
    }

    public function testAssertNotEmpty(): void
    {
        $this->assertTrue(Assert::that('1')->notEmpty()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('')->notEmpty();
    }

    public function testAssertIsString(): void
    {
        $this->assertTrue(Assert::that('1')->string()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that(1)->string();
    }

    public function testAssertIsInteger(): void
    {
        $this->assertTrue(Assert::that(1)->integer()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('1')->integer();
    }

    public function testAssertIsIntegerWithMessage(): void
    {
        $this->assertTrue(Assert::that(1)->integer()->verify());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value is not an integer');
        Assert::that('1')->integer('Value is not an integer');
    }

    public function testAssertIsMin(): void
    {
        $this->assertTrue(Assert::that(2)->min(1)->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that(1)->min(2);
    }

    public function testAssertIsMax(): void
    {
        $this->assertTrue(Assert::that(1)->max(2)->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that(2)->max(1);
    }

    public function testAssertIsBetween(): void
    {
        $this->assertTrue(Assert::that(4)->between(2, 8)->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that(1)->between(2, 3);
    }

    public function testAssertIsIn(): void
    {
        $this->assertTrue(Assert::that(1)->in([1, 2])->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that(1)->in([2, 3]);
    }

    public function testAssertIsNotIn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Assert::that(1)->notIn([1, 2]);
    }

    public function testAssertIsEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('test')->email();
    }

    public function testAssertIsUrl(): void
    {
        $this->assertTrue(Assert::that('http://example.com')->url()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('test')->url();
    }

    public function testAssertIsIp(): void
    {
        $this->assertTrue(Assert::that('127.0.0.1')->ip()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('test')->ip();
    }

    public function testAssertIsFloat(): void
    {
        $this->assertTrue(Assert::that(1.1)->float()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('1')->float();
    }

    public function testAssertIsBoolean(): void
    {
        $this->assertTrue(Assert::that(true)->boolean()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('1')->boolean();
    }

    public function testAssertIsNumeric(): void
    {
        $this->assertTrue(Assert::that('1')->numeric()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('test')->numeric();
    }

    public function testAssertIsArray(): void
    {
        $this->assertTrue(Assert::that([])->array()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('test')->array();
    }

    public function testAssertIsObject(): void
    {
        $this->assertTrue(Assert::that(new \stdClass())->object()->verify());
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('test')->object();
    }

    public function testAssertMatchesRegex(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Assert::that('test')->matchesRegex('/[0-9]/');
    }
}
