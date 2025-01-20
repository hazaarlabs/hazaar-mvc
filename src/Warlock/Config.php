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
        'sys' => [
            'id' => null,                 // Server ID is used to prevent clients from talking to the wrong server.
            'IDFile' => 'server.id',
            'applicationName' => 'hazaar', // The application is also used to prevent clients from talking to the wrong server.
            'autostart' => false,         // If TRUE the Warlock\Control class will attempt to autostart the server if it is not running.
            'pid' => 'server.pid',        // The name of the warlock process ID file relative to the application runtime directory.  For absolute paths prefix with /.
            'cleanup' => true,            // Enable/Disable message queue cleanup.
            'timezone' => 'UTC',          // The timezone of the server.  This is mainly used for scheduled tasks.
            'phpBinary' => null,          // Override path to the PHP binary file to use when executing tasks.
            'dateFormat' => 'c',
            'runtimePath' => '%RUNTIME_PATH%%DIRECTORY_SEPARATOR%warlock',
        ],
        'server' => [
            'listen' => '127.0.0.1',      // Server IP to listen on.  127.0.0.1 by default which only accept connections from localhost.  Use 0.0.0.0 to listen on all addresses.
            'port' => 8000,               // Server port to listen on.  The client will automatically attempt to connect on this port unluess overridden in the client section
            'encoded' => false,
            'win_bg' => false,
            'announce' => false,
        ],
        'kvstore' => [
            'enabled' => false,           // Enable the built-in key/value storage system.  Enabled by default.
            'persist' => false,           // If KVStore is enabled, this setting will enable restart persistent storage. Disabled by default.
            'namespace' => 'default',     // The namespace to persist.  Currently only one namespace is supported.
            'compact' => 0,               // Interval at which the persistent storage will be compacted to reclaim space.  Disabled by default.
        ],
        'client' => [
            'applicationName' => null,    // Server application name
            'connect' => true,            // Connect automatically on startup.  If FALSE, connect() must be called manually.
            'server' => null,             // Server address override.  By default the client will automatically figure out the addresss
            // based on the application config.  This can set it explicitly.
            'port' => null,               // Server port override.  By default the client will connect to the port in server->port.
            // Useful for reverse proxies or firewalls with port forward, etc.  Allows only the port to
            // be overridden but still auto generate the server part.
            'encoded' => false,
        ],
        'webClient' => [
            'applicationName' => null,
            'server' => null,
            'port' => null,
            'ssl' => false,                         // Use SSL to connect.  (wss://)
            'websockets' => true,                   // Use websockets.  Alternative is HTTP long-polling.
            'url' => null,                          // Resolved URL override.  This allows you to override the entire URL.  For the above auto
            // URL generator to work, this needs to be NULL.
            'check' => 60,                          // Send a PING if no data is received from the client for this many seconds
            'pingWait' => 5,                        // Wait this many seconds for a PONG before sending another PING
            'pingCount' => 3,                       // Disconnect after this many unanswered PING attempts
            'reconnect' => true,                    // When using WebSockets, automatically reconnect if connection is lost.
            'reconnectDelay' => 0,
            'reconnectRetries' => 0,
            'encoded' => null,
        ],
        'timeouts' => [
            'startup' => 1000,                     // Timeout for Warlock\Control to wait for the server to start
            'connect' => 5,                        // Timeout for Warlock\Control attempting to connect to a server.
        ],
        'admin' => [
            'trigger' => 'warlockadmintrigger',    // The name of the admin event trigger.  Only change this is you really know what you're doing.
            'key' => '0000',                       // The admin key.  This is a simple passcode that allows admin clients to do a few more things, like start/stop services, subscribe to admin events, etc.
        ],
        'log' => [
            'rrd' => 'server.rrd',                 // The RRD data file.  Used to store RRD data for graphing realtime statistics.
            'level' => 'W_ERR',                    // Default log level.  Allowed: W_INFO, W_WARN, W_ERR, W_NOTICE, W_DEBUG, W_DECODE, W_DECODE2.
            'file' => 'server.log',                // The log file to write to in the application runtime directory.
            'error' => 'server-error.log',         // The error log file to write to in the application runtime directory.  STDERR is redirected to this file.
            'rotate' => false,                     // Enable log file rotation
            'logfiles' => 7,                       // The maximum number of log files to keep
            'rotateAt' => '0 0 * * *',              // CRON schedule for when the log rotation will occur
        ],
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
        'event' => [
            'queueTimeout' => 5,                   // Message queue timeout.  Messages will hang around in the queue for this many seconds.  This allows late connections to
            // still get events and was the founding principle that allowed Warlock to work with long-polling HTTP connections.  Still
            // very useful in the WebSocket world though.
        ],
        'cluster' => [
            'enabled' => true,
            'name' => 'warlock',
            'accessKey' => null,
            'peers' => [],
        ],
    ];

    /**
     * @param array<mixed> $config
     */
    public function __construct(array $config = [], ?string $env = APPLICATION_ENV)
    {
        $defaultConfig = self::$defaultConfig;
        // @phpstan-ignore constant.notFound
        $defaultConfig['sys']['applicationName'] = APPLICATION_NAME;

        try {
            parent::__construct('warlock', $env, $defaultConfig);
        } catch (\Exception $e) {
            throw new \Exception('There is no warlock configuration file.  Warlock is disabled!');
        }

        if (!isset($this['sys']['id'])) {
            $this['sys']['id'] = $this->loadSystemID();
        }
        if (count($config) > 0) {
            $this->extend($config);
        }
    }

    public function generateSystemID(string $path): string
    {
        $hashes = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $hashes[] = md5_file($file->getPathname());
            }
        }
        $systemID = md5(implode('', $hashes));
        $systemIDFile = $this['sys']['runtimePath'].'/'.$this['sys']['IDFile'];
        file_put_contents($systemIDFile, $systemID);

        return $systemID;
    }

    private function loadSystemID(): string
    {
        $systemIDFile = $this['sys']['runtimePath'].'/'.$this['sys']['IDFile'];
        if (file_exists($systemIDFile)) {
            return file_get_contents($systemIDFile);
        }

        return (string) crc32(Loader::getFilePath(FilePath::APPLICATION));
    }
}
