<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Application;
use Hazaar\Application\FilePath;
use Hazaar\Loader;

/**
 * @brief       Control class for Warlock
 *
 * @detail      This class creates a connection to the Warlock server from within a Hazaar application allowing the
 *              application to send triggers or schedule tasks for delayed execution.
 *
 * @module      warlock
 */
class Control extends Process
{
    public Config $serverConfig;
    private string $cmd;
    private string $pidfile;
    private static ?string $guid = null;

    /**
     * @var array<string,Control>
     */
    private static array $instance = [];

    /**
     * @param array<mixed> $serverConfig
     */
    public function __construct(
        ?bool $autostart = null,
        array $serverConfig = [],
        ?string $instance_key = null,
        bool $require_connect = true
    ) {
        $this->serverConfig = new Config($serverConfig);
        if (!$instance_key) {
            $instance_key = hash('crc32b', $this->serverConfig['client']['server'].$this->serverConfig['client']['port']);
        }
        if (array_key_exists($instance_key, Control::$instance)) {
            throw new \Exception('There is already a control instance for this server:host.  Please use '.__CLASS__.'::getInstance()');
        }
        Control::$instance[$instance_key] = $this;
        $application = Application::getInstance();
        if (null === $this->serverConfig['client']['encoded']) {
            $this->serverConfig['client']['encoded'] = $this->serverConfig['server']['encoded'];
        }
        $protocol = new Protocol((string) $this->serverConfig['sys']->id, $this->serverConfig['client']['encoded']);
        $runtime_path = rtrim($this->serverConfig['sys']['runtimePath'], '/').DIRECTORY_SEPARATOR;
        if (!Control::$guid) {
            $guid_file = $runtime_path.'server.guid';
            if (file_exists($guid_file)) {
                Control::$guid = file_get_contents($guid_file);
            }
            // First we check to see if we need to start the Warlock server process
            if (null === $autostart) {
                $autostart = (bool) $this->serverConfig['sys']->autostart;
            }
            if (true === $autostart) {
                if (!$this->serverConfig['sys']['phpBinary']) {
                    $this->serverConfig['sys']['phpBinary'] = php_binary();
                }
                $this->pidfile = $runtime_path.$this->serverConfig['sys']['pid'];
                if (!$this->start()) {
                    throw new \Exception('Autostart of Warlock server has failed!');
                }
            }
        }
        parent::__construct($application, $protocol, Control::$guid);
        if (!$this->connected()) {
            if ($autostart) {
                throw new \Exception('Warlock was started, but we were unable to communicate with it.');
            }
            if (true === $require_connect) {
                throw new \Exception('Unable to communicate with Warlock.  Is it running?');
            }
        }
    }

    /**
     * @param array<mixed> $config
     */
    public static function getInstance(
        ?bool $autostart = null,
        array $config = [],
        bool $require_connect = true
    ): Control {
        $instance_key = hash('crc32b', ake($config, 'client.server').ake($config, 'client.port'));
        if (!array_key_exists($instance_key, Control::$instance)) {
            Control::$instance[$instance_key] = new Control($autostart, $config, $instance_key, $require_connect);
        }

        return Control::$instance[$instance_key];
    }

    public function isRunning(): bool
    {
        if (!$this->pidfile) {
            throw new \Exception('Can not check for running Warlock instance without PID file!');
        }
        if (!file_exists($this->pidfile)) {
            return false;
        }
        if (!($pid = (int) file_get_contents($this->pidfile))) {
            return false;
        }
        $proc_file = '/proc/'.$pid.'/stat';
        if (!file_exists($proc_file)) {
            return false;
        }
        $proc = file_get_contents($proc_file);

        return '' !== $proc && preg_match('/^'.preg_quote((string) $pid, '/').'\s+\(php\)/', $proc);
    }

    public function start(?int $timeout = null): bool
    {
        if (!$this->pidfile) {
            return false;
        }
        if ($this->isRunning()) {
            return true;
        }
        $env = [
            'APPLICATION_PATH' => Loader::getFilePath(FilePath::APPLICATION),
            'APPLICATION_ENV' => APPLICATION_ENV,
            'APPLICATION_ROOT' => Application::getRoot(),
            'WARLOCK_EXEC' => 1,
        ];
        $php_options = [];
        if (function_exists('xdebug_is_debugger_active') && \xdebug_is_debugger_active()) {
            $env['XDEBUG_CONFIG'] = 'remote_enable='.ini_get('xdebug.remote_enable')
                .' remote_handler='.ini_get('xdebug.remote_handler')
                .' remote_mode='.ini_get('xdebug.remote_mode')
                .' remote_port='.ini_get('xdebug.remote_port')
                .' remote_host='.ini_get('xdebug.remote_host')
                .' remote_cookie_expire_time='.ini_get('xdebug.remote_cookie_expire_time')
                .' profiler_enable='.ini_get('xdebug.profiler_enable');
        }
        $php_options[] = $server = dirname(__FILE__).DIRECTORY_SEPARATOR.'Server.php';
        if (!file_exists($server)) {
            throw new \Exception('Warlock server script could not be found!');
        }
        $this->cmd = $this->serverConfig['sys']['phpBinary'].' '.implode(' ', $php_options).'&';
        $env['WARLOCK_OUTPUT'] = 'file';
        foreach ($env as $name => $value) {
            putenv($name.'='.$value);
        }
        $start_check = time();
        // Start the server.  This should work on Linux and Windows
        shell_exec($this->cmd);
        if (!$timeout) {
            $timeout = $this->serverConfig['timeouts']->connect;
        }
        while (!$this->isRunning()) {
            if (time() > ($start_check + $timeout)) {
                return false;
            }
            usleep(100);
        }

        return true;
    }

    public function stop(): bool
    {
        if ($this->isRunning()) {
            $this->send('shutdown');
            if ('OK' == $this->recv($packet)) {
                $this->disconnect();

                return true;
            }
        }

        return false;
    }

    protected function connect(Protocol $protocol, ?string $guid = null): Connection\Socket
    {
        $headers = [];
        if (null !== $this->serverConfig['admin']['key']) {
            $headers['Authorization'] = 'Apikey '.base64_encode($this->serverConfig['admin']['key']);
        }
        if (null === $this->serverConfig['client']['port']) {
            $this->serverConfig['client']['port'] = $this->serverConfig['server']['port'];
        }
        /*
         * If no server is specified, look up the listen address of a local server config. This will override the
         * address AND the port.  This ensures configs that have a different browser client-side address can be configured
         * and work and the client side will connect to the correct localhost address/port
         */
        if (null === $this->serverConfig['client']['server']) {
            if ('0.0.0.0' == trim($this->serverConfig['server']['listen'])) {
                $this->serverConfig['client']['server'] = '127.0.0.1';
            } else {
                $this->serverConfig['client']['server'] = $this->serverConfig['server']['listen'];
            }
            $this->serverConfig['client']['port'] = $this->serverConfig['server']['port'];
            $this->serverConfig['client']['ssl'] = false; // Disable SSL because we know the server doesn't support it (yet?).
        }
        $conn = new Connection\Socket($protocol, Control::$guid);
        if (!$conn->connect($this->serverConfig['sys']['applicationName'], $this->serverConfig['client']['server'], $this->serverConfig['client']['port'], $headers)) {
            $conn->disconnect();
        }

        return $conn;
    }
}
