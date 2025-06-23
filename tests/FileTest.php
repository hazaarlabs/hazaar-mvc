<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application\Runtime;
use Hazaar\File;
use Hazaar\File\BTree;
use Hazaar\Util\GeoData;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class FileTest extends TestCase
{
    public function testTextFile(): void
    {
        $filename = 'UnitTestTempFile.txt';
        $file = new File(Runtime::getInstance()->getPath($filename));
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
        $file = new File(Runtime::getInstance()->getPath($filename));
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
        $file = Runtime::getInstance()->getPath('UnitTestEncryption.txt');
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
        $db = new BTree(Runtime::getInstance()->getPath('UnitTest.db'));
        $this->assertInstanceOf('\Hazaar\File\BTree', $db);
        $this->assertTrue($db->set('test', 'value'));
        $this->assertEquals('value', $db->get('test'));
        // $this->assertTrue($db->compact());
    }

    public function testGeoData(): void
    {
        $geo = new GeoData();
        $this->assertArrayHasKey('AU', $geo->countries());
        $countryInfo = $geo->countryInfo('AU');
        $this->assertArrayHasKey('currency', $countryInfo);
        $this->assertArrayHasKey('languages', $countryInfo);
        $this->assertArrayHasKey('name', $countryInfo);
        $this->assertArrayHasKey('phone_code', $countryInfo);
        $this->assertArrayHasKey('continent', $countryInfo);
        $this->assertArrayHasKey('capital', $countryInfo);
        $this->assertEquals('Australia', $geo->countryName('AU'));
        $this->assertIsArray($a = $geo->countryContinent('AU'));
        $this->assertEquals('Oceania', $a['name']);
        // Assert array contains en-AU
        $this->assertContains('en-AU', $geo->countryLanguages('AU'));
        $this->assertIsArray($s = $geo->states('AU'));
        $this->assertArrayHasKey('NSW', $geo->states('AU'));
        $this->assertEquals(61, $geo->countryPhoneCode('AU'));
    }

    public function testSharepointFileBackend(): void
    {
        // This test is a placeholder for SharePoint file backend tests.
        // Implement the actual test logic as needed.
        $this->markTestIncomplete('SharePoint file backend tests are not implemented yet.');
    }

    public function testGoogleDriveFileBackend(): void
    {
        // This test is a placeholder for Google Drive file backend tests.
        // Implement the actual test logic as needed.
        $this->markTestIncomplete('Google Drive file backend tests are not implemented yet.');
    }

    public function testDropboxFileBackend(): void
    {
        // This test is a placeholder for Dropbox file backend tests.
        // Implement the actual test logic as needed.
        $this->markTestIncomplete('Dropbox file backend tests are not implemented yet.');
    }

    public function testWebDAVFileBackend(): void
    {
        // This test is a placeholder for WebDAV file backend tests.
        // Implement the actual test logic as needed.
        $this->markTestIncomplete('WebDAV file backend tests are not implemented yet.');
    }
}
