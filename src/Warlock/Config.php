<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Application;
use Hazaar\Application\FilePath;
use Hazaar\Loader;

class Config extends Application\Config
{
    /**
     * @var array<mixed>
     */
    private static array $defaultConfig = [
        'server' => [
            'id' => 1,
            'listenAddress' => '0.0.0.0',
            'port' => 13080,
            'encode' => false,
            'phpBinary' => PHP_BINARY,
            'log' => [
                'level' => 'debug',
            ],
            'timezone' => 'UTC',
            'client' => [
                'check' => 60,
            ],
            'event' => [
                'cleanup' => true,
                'timeout' => 5, // Message queue timeout.  Messages will hang around in the queue for this many seconds.  This allows late connections to
                // still get events and was the founding principle that allowed Warlock to work with long-polling HTTP connections.  Still
                // very useful in the WebSocket world though.
            ],
        ],
        'cluster' => [
            'enabled' => false,
            'name' => 'warlock',
            'accessKey' => null,
            'peers' => [],
        ],
        'client' => [],
        'agent' => [
            'enabled' => true,
            'task' => [
                'retries' => 3,                        // Retry tasks that failed this many times.
                'retry' => 10,                         // Retry failed tasks after this many seconds.
                'expire' => 10,                        // Completed tasks will be cleaned up from the task queue after this many seconds.
                'boot_delay' => 5,                      // How long to hold off executing tasks scheduled to run on a reboot.  Can be used to allow services to finish starting.
            ],
            'process' => [
                'timeout' => 30,                       // Timeout for short run tasks initiated by the front end. Prevents runaway processes from hanging around.
                'limit' => 5,                          // Maximum number of concurrent tasks to execute.  THIS INCLUDES SERVICES.  So if this is 5 and you have 6 services, one service will never run!
                'exitWait' => 30,                       // How long the server will wait for processes to exit when shutting down.
            ],
            'service' => [
                'restarts' => 5,                       // Restart a failed service this many times before disabling it for a bit.
                'disable' => 300,                       // Disable a failed service for this many seconds before trying to start it up again.
            ],
        ],
        'kvstore' => [
            'enabled' => false,           // Enable the built-in key/value storage system.  Enabled by default.
            'persist' => false,           // If KVStore is enabled, this setting will enable restart persistent storage. Disabled by default.
            'namespace' => 'default',     // The namespace to persist.  Currently only one namespace is supported.
            'compact' => 0,               // Interval at which the persistent storage will be compacted to reclaim space.  Disabled by default.
        ],
    ];

    /**
     * @param array<mixed> $config
     */
    public function __construct(string $configFile = 'warlock', array $config = [], ?string $env = APPLICATION_ENV)
    {
        $defaultConfig = self::$defaultConfig;

        try {
            parent::__construct($configFile, $env, $defaultConfig);
        } catch (\Exception $e) {
            throw new \Exception('There is no warlock configuration file.  Warlock is disabled!');
        }
        if (!isset($this['server']['id'])) {
            $this['server']['id'] = $this->loadServerID();
        }
        if (count($config) > 0) {
            $this->extend($config);
        }
    }

    private function loadServerID(): string
    {
        $systemIDFile = $this['sys']['runtimePath'].'/'.$this['sys']['IDFile'];
        if (file_exists($systemIDFile)) {
            return file_get_contents($systemIDFile);
        }

        return (string) crc32(Loader::getFilePath(FilePath::APPLICATION));
    }
}
