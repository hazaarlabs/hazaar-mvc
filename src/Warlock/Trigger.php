<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;
use Hazaar\HTTP\Response;
use Hazaar\Map;

class Trigger
{
    public Config $serverConfig;
    private Client $client;
    private Protocol $protocol;

    /**
     * @param array<mixed> $serverConfig
     */
    public function __construct(null|array|Map $serverConfig = null)
    {
        $this->serverConfig = new Config($serverConfig);
        $this->client = new Client();
        if (null === $this->serverConfig['client']['encoded']) {
            $this->serverConfig['client']['encoded'] = $this->serverConfig['server']['encoded'];
        }
        $this->protocol = new Protocol((string) $this->serverConfig['sys']->id, $this->serverConfig['client']['encoded']);
    }

    public function send(string $event, mixed $data = null, mixed $options = null, ?Response &$response = null): bool
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
        $request->setBody($this->protocol->encode('TRIGGER', [
            'id' => $event,
            'data' => $data,
        ]));
        $response = $this->client->send($request);

        return 200 === $response->status;
    }
}
