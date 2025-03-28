<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Application;

/**
 * @brief       Control class for Warlock
 *
 * @detail      This class creates a connection to the Warlock server from within a Hazaar application allowing the
 *              application to send triggers or schedule tasks for delayed execution.
 *
 * @module      warlock
 */
class Client extends Process
{
    public Config $serverConfig;
    private static ?string $guid = null;

    /**
     * @var array<string,Client>
     */
    private static array $instance = [];

    /**
     * @param array<mixed> $serverConfig
     */
    public function __construct(
        array $serverConfig = [],
        ?string $instanceKey = null
    ) {
        $this->serverConfig = new Config($serverConfig);
        if (!$instanceKey) {
            $instanceKey = hash('crc32b', $this->serverConfig['client']['server'].$this->serverConfig['client']['port']);
        }
        if (array_key_exists($instanceKey, self::$instance)) {
            throw new \Exception('There is already a control instance for this server:host.  Please use '.__CLASS__.'::getInstance()');
        }
        self::$instance[$instanceKey] = $this;
        $application = Application::getInstance();
        if (null === $this->serverConfig['client']['encoded']) {
            $this->serverConfig['client']['encoded'] = $this->serverConfig['server']['encoded'];
        }
        $protocol = new Protocol((string) $this->serverConfig['sys']->id, $this->serverConfig['client']['encoded']);
        $runtimePath = rtrim($this->serverConfig['sys']['runtimePath'], '/').DIRECTORY_SEPARATOR;
        if (!self::$guid) {
            $guidFile = $runtimePath.'server.guid';
            if (file_exists($guidFile)) {
                self::$guid = file_get_contents($guidFile);
            }
        }
        parent::__construct($application, $protocol, self::$guid);
    }

    /**
     * @param array<mixed> $config
     */
    public static function getInstance(
        array $config = []
    ): self {
        $instanceKey = hash('crc32b', ($config['client']['server'] ?? '').($config['client']['port'] ?? ''));
        if (!array_key_exists($instanceKey, self::$instance)) {
            self::$instance[$instanceKey] = new self($config, $instanceKey);
        }

        return self::$instance[$instanceKey];
    }

    protected function createConnection(Protocol $protocol, ?string $guid = null): Connection\Socket
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

        return new Connection\Socket($protocol, self::$guid);
    }
}
