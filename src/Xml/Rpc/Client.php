<?php

namespace Hazaar\Xml\Rpc;

class Client extends \Hazaar\Http\Client {

    protected $location;

    protected $method_pfx = '';

    private   $lasterror  = NULL;

    function __construct($url, $method_pfx = '') {

        if(! in_array('xmlrpc', get_loaded_extensions())) {

            throw new Exception\XMLRPCNotInstalled();

        }

        $this->location = $url;

        $this->method_pfx = $method_pfx;

        parent::__construct();

    }

    public function __call($name, $args) {

        return $this->call($name, $args);

    }

    public function call($name, $args) {

        $method = ($this->method_pfx ? $this->method_pfx . '.' : '') . $name;

        $response = '';

        $request = new \Hazaar\Http\Request($this->location, 'POST', 'text/xml');

        $request->setBody(xmlrpc_encode_request($method, $args, ['version' => 'xmlrpc']));

        if($response = self::send($request)) {

            if($response->status == 200 && $result = xmlrpc_decode_request($response->body, $method)) {

                if((is_array($result) ? xmlrpc_is_fault($result) : FALSE)) {

                    throw new Exception\ClientException($result);

                }

            } else {

                $result = FALSE;

            }

        } else {

            throw new Exception\NoCommunication($this->url);

        }

        return $result;

    }

    public function getLastError() {

        return $this->lasterror;

    }

}
