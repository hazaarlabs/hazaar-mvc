<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application;
use Hazaar\Application\Runtime;
use Hazaar\File;
use Hazaar\File\Dir;
use Hazaar\File\Manager;
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
        $this->assertNotEquals($content, $encryptFile->getContents());
        unset($encryptFile);
        $decryptFile = new File($file);
        $this->assertTrue($decryptFile->isEncrypted());
        $this->assertEquals($content, $decryptFile->getContents());
        $this->assertTrue($decryptFile->decrypt());
        $this->assertFalse($decryptFile->isEncrypted());
        $this->assertTrue($decryptFile->unlink());
    }

    public function testDropboxFileBackendWithDirs(): void
    {
        $manager = $this->getDropboxManager();
        $this->assertTrue($manager->authorised());
        $this->assertTrue($manager->refresh(true));
        $this->assertInstanceOf(Dir::class, $manager->get('/'));
        if ($manager->exists('/test')) {
            $this->assertTrue($manager->unlink('/test'));
        }
        $this->assertTrue($manager->mkdir('/test'));
        $this->assertTrue($manager->exists('/test'));
        $this->assertInstanceOf(Dir::class, $manager->get('/test'));
        $this->assertTrue($manager->unlink('/test'));
        $this->assertFalse($manager->exists('/test'));
    }

    public function testDropboxFileBackendWithTextFile(): void
    {
        $manager = $this->getDropboxManager();
        $this->assertTrue($manager->authorised());
        $this->assertTrue($manager->refresh(true));
        if ($manager->exists('/example.txt')) {
            $this->assertTrue($manager->unlink('/example.txt'));
        }
        $exampleFile = $manager->get('/example.txt');
        $this->assertEquals(20, $exampleFile->putContents('This is a test file.'));
        $this->assertTrue($exampleFile->exists());
        $this->assertInstanceOf(File::class, $exampleFile);
        $this->assertEquals(20, $exampleFile->size());
        $this->assertEquals('This is a test file.', $exampleFile->getContents());
        $this->assertTrue($exampleFile->unlink());
    }

    public function testDropboxFileBackendWithImageFile(): void
    {
        $manager = $this->getDropboxManager();
        $this->assertTrue($manager->authorised());
        $this->assertTrue($manager->refresh(true));
        $imageFile = $manager->get('/circuitboard.jpg');
        $this->assertTrue($imageFile->exists());
        $this->assertNotEmpty($imageFile->getContents());
        $this->assertEquals('image/jpeg', $imageFile->mimeContentType());
        $thumb = $imageFile->thumbnailURL(100, 100);
    }

    public function testGoogleDriveFileBackend(): void
    {
        $manager = $this->getGoogleDriveManager();
        $this->assertTrue($manager->authorised());
        $this->assertTrue($manager->refresh());
        $this->assertInstanceOf(Dir::class, $manager->get('/'));
        if ($manager->exists('/test')) {
            $this->assertTrue($manager->unlink('/test'));
        }
        $this->assertTrue($manager->mkdir('/test'));
        $this->assertTrue($manager->exists('/test'));
        $this->assertInstanceOf(Dir::class, $manager->get('/test'));
        $this->assertTrue($manager->unlink('/test'));
        $this->assertFalse($manager->exists('/test'));
    }

    public function testGoogleDriveFileBackendWithTextFile(): void
    {
        $manager = $this->getGoogleDriveManager();
        $this->assertTrue($manager->authorised());
        $this->assertTrue($manager->refresh(true));
        if ($manager->exists('/example.txt')) {
            $this->assertTrue($manager->unlink('/example.txt'));
        }
        $exampleFile = $manager->get('/example.txt');
        $this->assertEquals(20, $exampleFile->putContents('This is a test file.'));
        $this->assertTrue($exampleFile->exists());
        $this->assertInstanceOf(File::class, $exampleFile);
        $this->assertEquals(20, $exampleFile->size());
        $this->assertEquals('This is a test file.', $exampleFile->getContents());
        $this->assertTrue($exampleFile->unlink());
    }

    public function testGoogleDriveFileBackendWithImageFile(): void
    {
        $manager = $this->getGoogleDriveManager();
        $this->assertTrue($manager->authorised());
        $this->assertTrue($manager->refresh(true));
        $imageFile = $manager->get('/circuitboard.jpg');
        $this->assertTrue($imageFile->exists());
        $this->assertNotEmpty($imageFile->getContents());
        $this->assertEquals('image/jpeg', $imageFile->mimeContentType());
    }

    public function testWebDAVFileBackend(): void
    {
        $manager = $this->getWebDAVManager();
        $this->assertTrue($manager->authorised());
        $this->assertTrue($manager->refresh(true));
        if (!$manager->exists('/testing')) {
            $this->assertTrue($manager->mkdir('/testing'));
        }
        $this->assertTrue($manager->exists('/testing'));
        $this->assertTrue($manager->isDir('/testing'));
        $file = $manager->get('/testing/hello.txt');
        $this->assertInstanceOf(File::class, $file);
        $this->assertEquals(20, $file->putContents('This is a test file.'));
        $this->assertTrue($file->exists());
        $this->assertTrue($file->unlink());
        $this->assertTrue($manager->unlink('/testing'));
        $this->assertFalse($manager->exists('/testing'));
    }

    public function testWebDAVFileBackendFileOperations(): void
    {
        $manager = $this->getWebDAVManager();
        $this->assertTrue($manager->authorised());
        $this->assertTrue($manager->refresh(true));
        if ($manager->exists('/hello.txt')) {
            $this->assertTrue($manager->isReadable('/hello.txt'));
            $this->assertTrue($manager->unlink('/hello.txt'));
        }
        $file = $manager->get('/hello.txt');
        $this->assertInstanceOf(File::class, $file);
        $file->putContents('Hello, World!');
        $this->assertTrue($file->exists());
        $this->assertEquals(13, $file->size());
        $this->assertEquals('Hello, World!', $file->getContents());
        $this->assertTrue($file->isReadable());
        $this->assertTrue($file->isWritable());
        $this->assertInstanceOf(File::class, $newFile = $file->copy('/hello_copy.txt'));
        $this->assertTrue($newFile->exists());
        if ($manager->exists('/testdir')) {
            $this->assertTrue($manager->unlink('/testdir'));
        }
        $this->assertTrue($manager->mkdir('/testdir'));
        $this->assertTrue($manager->exists('/testdir'));
        $this->assertTrue($manager->isDir('/testdir'));
        $this->assertInstanceOf(File::class, $copyFile = $newFile->copyTo('/testdir'));
        $this->assertTrue($copyFile->exists());
        $this->assertInstanceOf(File::class, $linkFile = $newFile->moveTo('/testdir/hello_moved.txt'));
        $this->assertTrue($linkFile->exists());
    }

    private function getWebDAVManager(): Manager
    {
        $config = Application::getInstance()->config->get('webdav');
        if (!$config) {
            $this->markTestSkipped('WebDAV configuration is not set.');
        }
        $manager = new Manager('webdav', $config);
        $manager->refresh(true);

        return $manager;
    }

    private function getDropboxManager(): Manager
    {
        $config = Application::getInstance()->config->get('dropbox');
        if (!$config) {
            $this->markTestSkipped('Dropbox configuration is not set.');
        }
        $manager = new Manager('dropbox', $config);
        if (!$manager->authorised()) {
            // Check if the access code is set in the environment variables
            // or in the configuration file.  Environment variables take precedence and
            // will be available in the CI environment.
            $accessCode = getenv('DROPBOX_ACCESS_CODE') ?: $config['access_code'] ?? '';
            $this->assertNotEmpty($accessCode, 'Dropbox tests require an access code from: '.$manager->buildAuthURL());
            $this->assertTrue(
                $manager->authoriseWithCode($accessCode),
                'Dropbox tests require a valid access code from: '.$manager->buildAuthURL()
            );
        }
        $manager->refresh(true);

        return $manager;
    }

    private function getGoogleDriveManager(): Manager
    {
        $config = Application::getInstance()->config->get('googledrive') ?? [];
        if (!$config) {
            $this->markTestSkipped('Google Drive configuration is not set.');
        }
        $manager = new Manager('googledrive', $config);
        if (!$manager->authorised()) {
            // Check if the access code is set in the environment variables
            // or in the configuration file.  Environment variables take precedence and
            // will be available in the CI environment.
            $accessCode = getenv('GOOGLE_DRIVE_ACCESS_CODE') ?: $config['access_code'] ?? '';
            $authURL = $manager->buildAuthURL($config['redirect_uri']);
            $this->assertNotEmpty($accessCode, 'Google Drive tests require an access code from: '.$authURL);
            $this->assertTrue(
                $manager->authoriseWithCode($accessCode, $config['redirect_uri']),
                'Google Drive tests require a valid access code from: '.$authURL
            );
        }
        $manager->refresh(true);

        return $manager;
    }
}
