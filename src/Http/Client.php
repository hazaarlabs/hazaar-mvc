<?php

namespace Hazaar\Http;

class Client {

    private $debug              = FALSE;

    private $lasterror          = NULL;

    private $buffer_size        = 4096;

    private $connection_timeout = 5;

    private $headers            = array();

    private $cookie             = NULL;

    private $context            = NULL;

    private $auto_redirect      = TRUE;

    private $redirect_methods   = array(
        'GET',
        'OPTIONS'
    );

    private $username;

    private $password;

    private $auth;

    function __construct($local_cert = NULL, $passphrase = NULL, $debug = FALSE) {

        $this->context = stream_context_create();

        if($local_cert) {

            if(! file_exists($local_cert)) {

                throw new Exception\CertificateNotFound();

            }

            stream_context_set_option($this->context, 'ssl', 'local_cert', $local_cert);

            if($passphrase) {

                stream_context_set_option($this->context, 'ssl', 'passphrase', $passphrase);

            }

        }

        $this->debug = $debug;

    }

    public function auth($username, $password) {

        $this->username = $username;

        $this->password = $password;

    }

    public function authorisation(\Hazaar\Auth\Adapter $auth){

        $this->auth = $auth;

    }

    public function authorization(\Hazaar\Auth\Adapter $auth){

        return $this->authorisation($auth);

    }

    public function setHeader($header, $value) {

        $this->headers[$header] = $value;

    }

    public function options($uri) {

        $request = new Request($uri, 'OPTIONS');

        if($this->cookie)
            $request->setHeader('Cookie', $this->cookie);

        return $this->send($request);

    }

    public function get($uri, $redirect_limit = 10, $offset = -1, $maxlen = NULL) {

        $request = new Request($uri, 'GET');

        if($this->cookie)
            $request->setHeader('Cookie', $this->cookie);

        if($offset >= 0) {

            $range = 'bytes=' . $offset . '-';

            if($maxlen)
                $range .= ($offset + ($maxlen - 1));

            $request->setHeader('Range', $range);

        }

        return $this->send($request, $redirect_limit);

    }

    public function head($uri, $redirect_limit = 10) {

        $request = new Request($uri, 'HEAD');

        if($this->cookie)
            $request->setHeader('Cookie', $this->cookie);

        return $this->send($request, $redirect_limit);

    }

    public function post($uri, $data = NULL, $datatype = NULL, $redirect_limit = 0) {

        $request = new Request($uri, 'POST');

        if(! $datatype)
            $datatype = 'text/html';

        if($data)
            $request->setBody($data);

        if($datatype)
            $request->setHeader('Content-Type', $datatype);

        if($this->cookie)
            $request->setHeader('Cookie', $this->cookie);

        return $this->send($request, $redirect_limit);

    }

    public function put($uri, $data = NULL, $datatype = NULL) {

        $request = new Request($uri, 'PUT');

        if(! $datatype && is_array($data))
            $datatype = 'text/html';

        if($data)
            $request->setBody($data);

        if($datatype)
            $request->setHeader('Content-Type', $datatype);

        if($this->cookie)
            $request->setHeader('Cookie', $this->cookie);

        return $this->send($request);

    }

    public function delete($url) {

        $request = new Request($url, 'DELETE');

        if($this->cookie)
            $request->setHeader('Cookie', $this->cookie);

        return $this->send($request);

    }

    public function trace($url) {

        $request = new Request($url, 'TRACE');

        if($this->cookie)
            $request->setHeader('Cookie', $this->cookie);

        return $this->send($request);

    }

    public function send(Request $request, $redirect_limit = 10) {

        if(! $request instanceof Request)
            return FALSE;

        if(is_array($this->headers) && count($this->headers) > 0) {

            foreach($this->headers as $header => $value)
                $request->setHeader($header, $value);

        }

        if($this->auth)
            $request->authorisation($this->auth);

        elseif($this->username)
            $request->authorise($this->username, $this->password);

        $sck_fd = stream_socket_client($request->fsock_host, $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $this->context);

        if($sck_fd) {

            $http_request = $request->toString();

            fputs($sck_fd, $http_request, strlen($http_request));

            $response = new Response();

            $response->setSource($request->uri);

            $buffer_size = $this->buffer_size;

            while(($buf = fread($sck_fd, $buffer_size)) !== FALSE) {

                $response->read($buf);

                /*
                 * Dynamic buffer resize.  This fixes a problem when the bytes remaining is less than the buffer size,
                 * but the buffer has already been read.
                 *
                 * If we have a content-length and the buffer len is greater or equal to it, dump out as we have all our
                 * content.
                 */
                if($response->bytes_remaining > 0 && $response->bytes_remaining < $buffer_size) {

                    $buffer_size = $response->bytes_remaining;

                }

                //If the socket is now EOF then break out
                if(feof($sck_fd))
                    break;

            }

            fclose($sck_fd);

            if($this->auto_redirect && ($response->status == 301 || $response->status == 302)) {

                if(in_array($request->method, $this->redirect_methods)) {

                    if($redirect_limit <= 0) {

                        throw new Exception\TooManyRedirects();

                    }

                    if(! preg_match('/^http[s]?\:\/\//', $response->location)) {

                        if(substr($response->location, 0, 1) == '/') {

                            $request->uri->path = $response->location;

                        } else {

                            $request->uri->path .= ((substr($request->url->path, -1, 1) == '/') ? NULL : '/') . $response->location;

                        }

                    } else {

                        $request->uri = new Uri($response->location);

                    }

                    return self::send($request, --$redirect_limit);

                }

                throw new Exception\RedirectNotAllowed($response->status);

            } elseif($response->status == 303) {

                $request->method = 'GET';

                return $this->get($response->location, --$redirect_limit);

            }

        } else {

            throw new \Exception('Error #' . $errno . ': ' . $errstr);

        }

        return $response;

    }

    public function getLastError() {

        return $this->lasterror;

    }

    public function setCookie($cookie) {

        $this->cookie = $cookie;

    }

    public function cacheCookie(\Hazaar\Cache $cache) {

        if(! $this->cookie)
            return FALSE;

        $cache->set('xmlrpc-cookie', $this->cookie);

        return TRUE;

    }

    public function uncacheCookie(\Hazaar\Cache $cache) {

        if($this->cookie = $cache->get('xmlrpc-cookie')) {

            return TRUE;

        }

        return FALSE;

    }

    public function disableRedirect() {

        $this->auto_redirect = FALSE;

    }

}