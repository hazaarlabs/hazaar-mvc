<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application;
use Hazaar\File\BTree;
use Hazaar\File;
use Hazaar\GeoData;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class FileTest extends TestCase
{
    public function testTextFile(): void
    {
        $filename = 'UnitTestTempFile.txt';
        $file = new File(Application::getInstance()->getRuntimePath($filename));
        if ($file->exists()) {
            $file->unlink();
        }
        $this->assertInstanceOf('\Hazaar\File', $file);
        $this->assertEquals($filename, $file->basename());
        $this->assertEquals('txt', $file->extension());
        $this->assertEquals('text/plain', $file->mimeContentType());
        $this->assertEquals(0, $file->size());
        $this->assertFalse($file->isReadable());
        $this->assertFalse($file->isWritable());
        $this->assertEquals(100, $file->putContents(implode('', array_fill(0, 100, '.'))));
        $this->assertEquals(100, $file->size());
        $this->assertTrue($file->unlink());
    }

    public function testJSONFile(): void
    {
        $filename = 'UnitTestTempFile.json';
        $file = new File(Application::getInstance()->getRuntimePath($filename));
        if ($file->exists()) {
            $file->unlink();
        }
        $this->assertInstanceOf('\Hazaar\File', $file);
        $this->assertEquals($filename, $file->basename());
        $this->assertEquals('json', $file->extension());
        $this->assertEquals('application/json', $file->mimeContentType());
        $this->assertEquals(0, $file->size());
        $this->assertFalse($file->isReadable());
        $this->assertFalse($file->isWritable());
        $testObject = [
            'test' => 'value',
            'array' => [1, 2, 3],
        ];
        $this->assertEquals(32, $file->putContents(json_encode($testObject)));
        $this->assertEquals(32, $file->size());
        $this->assertInstanceOf('stdClass', $json = $file->parseJSON());
        $this->assertEquals('value', $json->test);
        $this->assertIsArray($json->array);
        $this->assertTrue($file->unlink());
    }

    public function testEncryptedFile(): void
    {
        $file = Application::getInstance()->getRuntimePath('UnitTestEncryption.txt');
        $encryptFile = new File($file);
        if ($encryptFile->exists()) {
            $encryptFile->unlink();
        }
        $this->assertFalse($encryptFile->isReadable());
        $this->assertFalse($encryptFile->isWritable());
        $content = implode('', array_fill(0, 100, '.'));
        $this->assertEquals(strlen($content), $encryptFile->putContents($content));
        $this->assertEquals(strlen($content), $encryptFile->size());
        $this->assertFalse($encryptFile->isEncrypted());
        $this->assertTrue($encryptFile->encrypt());
        $this->assertTrue($encryptFile->isEncrypted());
        unset($encryptFile);
        $decryptFile = new File($file);
        $this->assertTrue($decryptFile->isEncrypted());
        $this->assertEquals($content, $decryptFile->getContents());
        $this->assertTrue($decryptFile->decrypt());
        $this->assertFalse($decryptFile->isEncrypted());
        $this->assertTrue($decryptFile->unlink());
    }

    public function testBTreeFile(): void
    {
        $db = new BTree(Application::getInstance()->getRuntimePath('UnitTest.db'));
        $this->assertInstanceOf('\Hazaar\File\BTree', $db);
        $this->assertTrue($db->set('test', 'value'));
        $this->assertEquals('value', $db->get('test'));
        // $this->assertTrue($db->compact());
    }

    public function testGeoData(): void
    {
        $geo = new GeoData();
        $this->assertInstanceOf('\Hazaar\GeoData', $geo);
        $this->assertIsArray($geo->countries());
        $this->assertArrayHasKey('AU', $geo->countries());
        $this->assertIsArray($geo->countryInfo('AU'));
        $this->assertEquals('Australia', $geo->countryName('AU'));
        $this->assertIsArray($a = $geo->countryContinent('AU'));
        $this->assertEquals('Oceania', $a['name']);
        $this->assertIsArray($geo->countryLanguages('AU'));
        $this->assertIsArray($s = $geo->states('AU'));
        $this->assertArrayHasKey('NSW', $geo->states('AU'));
        $this->assertIsArray($geo->cities('AU', 'NSW'));
        $this->assertEquals(61, $geo->countryPhoneCode('AU'));
    }
}
