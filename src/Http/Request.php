<?php

namespace Hazaar\Http;

class Request extends \Hazaar\Map {

    public  $method  = 'GET';

    private $headers = array(
        'User-Agent'   => 'hazaarmvc-php/' . HAZAAR_VERSION . ' (PHP)',
        'Content-Type' => 'text/html',
        'Connection'   => 'close'
    );

    private  $raw_uri;

    private  $uri;

    private  $fsock_host;

    private  $body    = NULL;

    function __construct($uri = NULL, $method = 'GET', $content_type = NULL) {

        if($method)
            $this->method = $method;

        if(! $content_type) {

            if($this->method == 'POST') {

                $content_type = 'application/x-www-form-urlencoded';

            } else {

                $content_type = 'text/html';

            }

        }

        $this->setHeader('Content-Type', $content_type);

        $this->uri($uri);

        //Check for cached cookies
        if($this->uri){

            if($path = \Hazaar\Application::getInstance()->runtimePath('cache')){

                $cache_file = $path . DIRECTORY_SEPARATOR . 'http_cookie_' . $this->uri->host();

                if(file_exists($cache_file)){

                    $cookie = file_get_contents($cache_file);

                    if($this->useCookie($cookie))
                        $this->setHeader('Cookie', $cookie);

                }

            }

        }

    }

    public function uri($uri = null){

        if($uri === NULL)
            return $this->uri;

        $this->raw_uri = $uri;

        $this->uri = ($uri instanceof Uri) ? $uri : new Uri($uri);

        /**
         * !!NOTE!!
         * This is a hack to prevent bad hosts from throwing a FATAL error when trying to connect to them.
         * I don't know why a FATAL error is thrown just because host we're connecting to is not found, but this
         * will check that the hostname exists first before trying to continue.
         */
        if(ip2long(gethostbyname($this->uri->host())) == false)
            throw new Exception\HostNotFound($this->uri->host());

        //If the protocol ends in s, we're going to assume it is meant for SSL
        if($this->uri->isSecure()) {

            $this->fsock_host = 'ssl://' . $this->uri->host() . ':' . $this->uri->port();

        } else {

            $this->fsock_host = 'tcp://' . $this->uri->host() . ':' . $this->uri->port();

        }

        $this->setHeader('Host', $this->uri->host());

        return $this->uri;

    }

    private function useCookie($cookie) {

        $cookie_vars = preg_split('/;\s?/', $cookie);

        foreach($cookie_vars as $var) {

            if(preg_match('/(\w*)[=]?(.*)/', $var, $matches)) {

                $key = strtolower($matches[1]);

                switch($key) {

                    case 'httponly' :
                        if(substr($this->uri->scheme(), 0, 4) != 'http') {

                            return FALSE;

                        }

                        break;

                    case 'secure' :
                        if(! $this->uri->isSecure()) {

                            return FALSE;

                        }

                        break;
                }

            }

        }

        return TRUE;

    }

    public function addMultipart($data, $content_type = NULL) {

        if(! $content_type) {

            if(is_array($data))
                $content_type = 'application/json';
            else
                $content_type = 'text/text';

        }

        if($this->body && ! is_array($this->body)) {

            $part = array($this->getHeader('Content-Type'), $this->body);

            $this->body = array($part);

        }

        $part = array($content_type, $data);

        if(! is_array($this->body))
            $this->body = array();

        $this->body[] = $part;

    }

    public function getHost(){

        return $this->fsock_host;

    }

    public function setBody($body) {

        $this->body = (string)$body;

    }

    public function getBody() {

        return $this->body;

    }

    public function getHeader($key) {

        return $this->headers[$key];

    }

    public function setHeader($key, $value) {

        $this->headers[$key] = $value;

    }

    public function toString() {

        $uri = clone $this->uri;

        $body = '';

        /*
         * Convert any parameters into a HTTP POST query
         */

        if($this->method == 'GET' && $this->count() > 0) {

            $uri->setParams($this->toArray());

        } else {

            if($this->body) {

                if(count($this->elements) > 0) {

                    if(! is_array($this->body)) {

                        $saved_body = $this->body;

                        $this->body = array();

                    }

                    $content_type = $this->getHeader('Content-Type');

                    switch($content_type) {

                        case 'text/json' :


                        case 'application/json' :
                        case 'application/javascript' :
                        case 'application/x-javascript' :

                            $this->body[] = array($content_type, $this->toJSON());

                            break;

                        case 'text/html' :
                        case 'application/x-www-form-urlencoded':
                        default:

                            $elements = array();

                            foreach($this->elements as $key => $value) {

                                $elements[] = array(
                                    array('Content-Disposition' => 'form-data; name="' . $key . '"'),
                                    $value
                                );

                            }

                            $this->body = array_merge($elements, $this->body);

                            break;

                    }

                    if(isset($saved_body))
                        $this->body[] = array($content_type, $saved_body);

                }

                if(is_array($this->body)) {

                    if(count($this->body[0]) == 2) {

                        $boundary = 'HazaarMVCMultipartBoundary' . uniqid();

                        $this->setHeader('Content-Type', 'multipart/form-data; boundary="' . $boundary . '"');

                        foreach($this->body as $part) {

                            $body .= "--$boundary\n";

                            if(! is_array($part[0]))
                                $part[0] = array('Content-Type' => $part[0]);

                            if($content_type = ake($part[0], 'Content-Type')) {

                                switch($content_type) {

                                    case 'text/json' :


                                    case 'application/json' :

                                        $data = json_encode($part[1]);

                                        break;

                                    case 'text/html' :
                                    case 'application/x-www-form-urlencoded':

                                        $data = is_array($part[1]) ? http_build_query($part[1]) : $part[1];

                                        break;

                                    default:

                                        $data =& $part[1];

                                        break;

                                }

                            } else {

                                $data = $part[1];

                            }

                            foreach($part[0] as $key => $value)
                                $body .= $key . ': ' . $value . "\n";

                            $body .= "\n$data\n";

                        }

                        $body .= "--$boundary--\n";

                    } else {

                        $this->setHeader('Content-Type', 'application/json');

                        $body = json_encode($this->body);

                    }

                } else { //Otherwise use the raw content body

                    $body = $this->body;

                }

            } elseif($this->count() > 0) {

                switch($this->getHeader('Content-Type')) {

                    case 'text/json' :


                    case 'application/json' :
                    case 'application/javascript' :
                    case 'application/x-javascript' :

                        $body = $this->toJSON();

                        break;

                    case 'text/html' :
                    case 'application/x-www-form-urlencoded':
                    default:

                        $body = http_build_query($this->toArray());

                        break;

                }

            }

        }

        //Always include a content-length header.  Fixes POST to IIS returning 411 (length required).
        $this->setHeader('Content-Length', strlen($body));

        /*
         * Build the header section
         */
        $access_uri = $uri->path() . $uri->queryString();

        $http_request = "{$this->method} {$access_uri} HTTP/1.1\r\n";

        foreach($this->headers as $header => $value)
            $http_request .= $header . ': ' . $value . "\r\n";

        $http_request .= "\r\n" . $body;

        return $http_request;

    }

    public function authorise($username, $password, $method = 'basic') {

        if($method = 'basic') {

            $auth = ucfirst($method) . ' ' . base64_encode($username . ':' . $password);

        }

        $this->setHeader('Authorization', $auth);

    }

    public function authorization($user, $type = 'Bearer'){

        return $this->authorisation($user, $type);

    }

    public function authorisation($user, $type = null){

        if($user instanceof \Hazaar\Auth\Adapter){

            if($token = $user->getToken()){

                if(!$type)
                    $type = $user->getTokenType();

                $this->setHeader('Authorization', $type . ' ' . $token);

                return true;

            }

        }

        return false;

    }

}