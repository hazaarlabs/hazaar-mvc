<?php

declare(strict_types=1);

namespace Hazaar\Tests\DBI;

use Hazaar\Application\Config;
use Hazaar\DBI\Adapter;
use Hazaar\File;
use Hazaar\File\Backend\DBI;
use Hazaar\File\Dir;
use Hazaar\File\Manager;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class FileBackendTest extends TestCase
{
    private Adapter $dbi;

    public function setUp(): void
    {
        $config = Config::getInstance('database');
        if (!$config->get('type')) {
            $this->markTestSkipped('DBI configuration is not set.');
        }
        $this->dbi = new Adapter();
        if (!$this->dbi->tableExists('hz_file')) {
            $manager = $this->dbi->getSchemaManager();
            $migrationResult = $manager->migrateDBIFileBackend();
            $this->assertTrue($migrationResult, 'DBI file backend migration failed');
        }
    }

    public function testDBITablesExist(): void
    {
        $this->assertTrue($this->dbi->tableExists('hz_file'));
        $this->assertTrue($this->dbi->tableExists('hz_file_chunk'));
    }

    public function testFileBackendOperations(): void
    {
        $manager = new Manager('dbi');
        $this->assertInstanceOf(Manager::class, $manager);
        $this->assertInstanceOf(DBI::class, $manager->getBackend());
    }

    public function testFileBackendListDirectories(): void
    {
        $manager = new Manager('dbi');
        $directories = $manager->dir('/');
        $this->assertInstanceOf(Dir::class, $directories);
    }

    public function testFileBackendCreateAndDeleteFile(): void
    {
        $manager = new Manager('dbi');
        $file = $manager->get('/test.txt');
        if ($file->exists()) {
            $file->unlink();
        }
        $this->assertEquals(13, $file->setContents('Hello, World!'));
        $this->assertEquals(13, $file->save());
        $this->assertTrue($file->exists());
        $this->assertEquals('Hello, World!', $file->getContents());
        $this->assertTrue($file->unlink());
        $this->assertFalse($file->exists());
    }

    public function testFileBackendFSCK(): void
    {
        $manager = new Manager('dbi');
        $fsckResult = $manager->fsck();
        $this->assertTrue($fsckResult, 'File system check failed');
    }

    public function testFileCopy(): void
    {
        $manager = new Manager('dbi');
        $sourceFile = $manager->get('/test.txt');
        if (!$sourceFile->exists()) {
            $sourceFile->putContents('This is a source file for copy test.');
        }
        $this->assertTrue($sourceFile->exists());
        $this->assertInstanceOf(File::class, $destinationFile = $sourceFile->copyTo('/copy_of_test.txt', true));
        $this->assertTrue($destinationFile->exists());
        $this->assertEquals('This is a source file for copy test.', $destinationFile->getContents());
        $sourceFile->putContents('This is an updated source file for copy test.');
        $this->assertInstanceOf(File::class, $destinationFile = $sourceFile->copyTo('/copy_of_test.txt', true));
        $this->assertEquals('This is an updated source file for copy test.', $destinationFile->getContents());
        $this->assertTrue($destinationFile->unlink());
        $this->assertFalse($destinationFile->exists());
        $this->assertTrue($sourceFile->unlink());
        $this->assertFalse($sourceFile->exists());
    }
}
