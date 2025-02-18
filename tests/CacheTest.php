<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application;
use Hazaar\Cache\Adapter;
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
        $cache = new Adapter('chain', $options);
        $this->assertTrue($cache->set('test', 'value'));
        $this->assertEquals('value', $cache->get('test'));
    }

    public function testFileCache(): void
    {
        $options = [
            'path' => Application::getInstance()->getRuntimePath('cache'),
        ];
        $cache = new Adapter('file', $options);
        $this->assertTrue($cache->set('test', 'value'));
        $this->assertEquals('value', $cache->get('test'));
    }

    // public function testSHMCached(): void
    // {
    //     $options = [
    //         'namespace' => 'test',
    //     ];
    //     $cache = new Adapter('shm', $options);
    //     $this->assertTrue($cache->set('test', 'value'));
    //     $this->assertEquals('value', $cache->get('test'));
    // }

    // public function testAPCCached(): void
    // {
    //     $options = [
    //         'namespace' => 'test',
    //     ];
    //     $cache = new Adapter('apc', $options);
    //     $this->assertTrue($cache->set('test', 'value'));
    //     $this->assertEquals('value', $cache->get('test'));
    // }
}
