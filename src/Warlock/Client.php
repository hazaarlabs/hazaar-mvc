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
    public Config $config;
    private static ?string $guid = null;

    /**
     * @param array<mixed> $config
     */
    public function __construct(
        array $config = []
    ) {
        $this->config = new Config($config);
        $application = Application::getInstance();
        if (null === $this->config['client']['encoded']) {
            $this->config['client']['encoded'] = $this->config['server']['encoded'];
        }
        $protocol = new Protocol((string) $this->config['sys']['id'], $this->config['client']['encoded']);
        $runtimePath = rtrim($this->config['sys']['runtimePath'], '/').DIRECTORY_SEPARATOR;
        if (!self::$guid) {
            $guidFile = $runtimePath.'server.guid';
            if (file_exists($guidFile)) {
                self::$guid = file_get_contents($guidFile);
            }
        }
        parent::__construct($application, $protocol, self::$guid);
    }

    protected function createConnection(Protocol $protocol, ?string $guid = null): Connection\Socket
    {
        $headers = [];
        if (null !== $this->config['admin']['key']) {
            $headers['Authorization'] = 'Apikey '.base64_encode($this->config['admin']['key']);
        }
        if (null === $this->config['client']['port']) {
            $this->config['client']['port'] = $this->config['server']['port'];
        }
        /*
         * If no server is specified, look up the listen address of a local server config. This will override the
         * address AND the port.  This ensures configs that have a different browser client-side address can be configured
         * and work and the client side will connect to the correct localhost address/port
         */
        if (null === $this->config['client']['server']) {
            if ('0.0.0.0' == trim($this->config['server']['listen'])) {
                $this->config['client']['server'] = '127.0.0.1';
            } else {
                $this->config['client']['server'] = $this->config['server']['listen'];
            }
            $this->config['client']['port'] = $this->config['server']['port'];
            $this->config['client']['ssl'] = false; // Disable SSL because we know the server doesn't support it (yet?).
        }

        return new Connection\Socket($protocol, self::$guid);
    }
}
