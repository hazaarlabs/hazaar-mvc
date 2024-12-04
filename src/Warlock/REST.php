<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;
use Hazaar\HTTP\Response;

class REST
{
    public Config $serverConfig;
    private Client $client;
    private Protocol $protocol;

    /**
     * @param array<mixed> $serverConfig
     */
    public function __construct(array $serverConfig = [])
    {
        $this->serverConfig = new Config($serverConfig);
        $this->client = new Client();
        if (null === $this->serverConfig['client']['encoded']) {
            $this->serverConfig['client']['encoded'] = $this->serverConfig['server']['encoded'];
        }
        $this->protocol = new Protocol((string) $this->serverConfig['sys']->id, $this->serverConfig['client']['encoded']);
    }

    /**
     * Triggers an event and sends the corresponding data.
     *
     * @param string        $event     the event identifier
     * @param mixed         $data      The data to be sent with the event. Default is null.
     * @param mixed         $options   Additional options for the event. Default is null.
     * @param null|Response &$response A reference to a Response object that will be populated. Default is null.
     *
     * @return bool returns true if the event was successfully triggered, false otherwise
     */
    public function trigger(string $event, mixed $data = null, mixed $options = null, ?Response &$response = null): bool
    {
        return $this->send('TRIGGER', ['id' => $event, 'data' => $data], $response);
    }

    /**
     * Sends a request to the configured server.
     *
     * This method constructs a URL based on the server configuration and sends a POST request
     * with the specified type and data. It handles cases where the client server or port is not
     * specified by defaulting to the server's listen address and port. Additionally, it sets up
     * the authorization header if an admin key is provided.
     *
     * @param string        $type      the type of the request
     * @param mixed         $data      The data to be sent with the request. Default is null.
     * @param mixed         $options   Additional options for the request. Default is null.
     * @param null|Response &$response The response object that will be populated with the server's response. Default is null.
     *
     * @return bool returns true if the response status is 200, otherwise false
     */
    private function send(string $type, mixed $data = null, mixed $options = null, ?Response &$response = null): bool
    {
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
        $url = 'http://'.$this->serverConfig['client']['server']
            .':'.$this->serverConfig['client']['port']
            .'/'.$this->serverConfig['sys']['applicationName'].'/warlock';
        $request = new Request($url, 'POST');
        if (null !== $this->serverConfig['admin']['key']) {
            $request->setHeader('Authorization', 'Apikey '.base64_encode($this->serverConfig['admin']['key']));
        }
        $request->setBody($this->protocol->encode($type, $data));
        $response = $this->client->send($request);

        return 200 === $response->status;
    }
}
