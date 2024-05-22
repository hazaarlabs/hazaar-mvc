<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\XML\Element;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class XMLTest extends TestCase
{
    private string $xmlString = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n<test xmlns:0=\"unittest\">testing123</test>";

    public function testCreateXML(): void
    {
        $xml = new Element('unittest:test', 'testing123', ['unittest']);
        $this->assertInstanceOf('Hazaar\XML\Element', $xml);
        $this->assertEquals('test', $xml->getName());
        $this->assertEquals('unittest', $xml->getNamespace());
        $this->assertEquals($this->xmlString, $xml->toXML());
    }

    public function testParseXML(): void
    {
        $xml = new Element();
        $this->assertInstanceOf('Hazaar\XML\Element', $xml);
        $this->assertTrue($xml->loadXML($this->xmlString));
        $this->assertEquals('test', $xml->getName());
        //$this->assertEquals('unittest', $xml->getNamespace());
    }
}
