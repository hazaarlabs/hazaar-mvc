<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application;
use Hazaar\Cache;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class CacheTest extends TestCase
{
    public function testChainCache(): void
    {
        $options = [
            'backends' => [
                'apc' => [
                ],
                'file' => [
                    'path' => Application::getInstance()->getRuntimePath('cache'),
                ],
            ],
        ];
        $cache = new Cache('chain', $options);
        $this->assertTrue($cache->set('test', 'value'));
        $this->assertEquals('value', $cache->get('test'));
    }

    public function testFileCache(): void
    {
        $options = [
            'path' => Application::getInstance()->getRuntimePath('cache'),
        ];
        $cache = new Cache('file', $options);
        $this->assertTrue($cache->set('test', 'value'));
        $this->assertEquals('value', $cache->get('test'));
    }

    // public function testSHMCached(): void
    // {
    //     $options = [
    //         'namespace' => 'test',
    //     ];
    //     $cache = new Cache('shm', $options);
    //     $this->assertTrue($cache->set('test', 'value'));
    //     $this->assertEquals('value', $cache->get('test'));
    // }

    // public function testAPCCached(): void
    // {
    //     $options = [
    //         'namespace' => 'test',
    //     ];
    //     $cache = new Cache('apc', $options);
    //     $this->assertTrue($cache->set('test', 'value'));
    //     $this->assertEquals('value', $cache->get('test'));
    // }
}
