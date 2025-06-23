<?php

declare(strict_types=1);

namespace Hazaar\Tests\DBI;

use Hazaar\DBI\Adapter;
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
}
