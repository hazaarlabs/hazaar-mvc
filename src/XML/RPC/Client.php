<?php

declare(strict_types=1);

namespace Hazaar\XML\RPC;

use Hazaar\HTTP\Request;
use Hazaar\HTTP\URL;
use Hazaar\XML\RPC\Exception\ClientException;
use Hazaar\XML\RPC\Exception\NoCommunication;
use Hazaar\XML\RPC\Exception\XMLRPCNotInstalled;

class Client extends \Hazaar\HTTP\Client
{
    protected URL $location;
    protected string $method_pfx = '';

    public function __construct(URL $url, string $method_pfx = '')
    {
        if (!in_array('xmlrpc', get_loaded_extensions())) {
            throw new XMLRPCNotInstalled();
        }
        $this->location = $url;
        $this->method_pfx = $method_pfx;
        parent::__construct();
    }

    /**
     * @param array<mixed> $args
     */
    public function __call(string $name, array $args): mixed
    {
        return $this->call($name, $args);
    }

    /**
     * @param array<mixed> $args
     */
    public function call(string $name, array $args): mixed
    {
        $method = ($this->method_pfx ? $this->method_pfx.'.' : '').$name;
        $response = '';
        $request = new Request($this->location, 'POST', 'text/xml');
        $request->setBody(xmlrpc_encode_request($method, $args, ['version' => 'xmlrpc']));
        if ($response = self::send($request)) {
            if (200 == $response->status
                && ($result = xmlrpc_decode_request($response->body, $method))) {
                if (xmlrpc_is_fault($result)) {
                    throw new ClientException($result);
                }
            } else {
                $result = false;
            }
        } else {
            throw new NoCommunication($this->location);
        }

        return $result;
    }
}
