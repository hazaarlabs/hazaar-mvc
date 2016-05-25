<?php

namespace Hazaar\Google;

class OAuth {

    private $params = array();

    private $session;

    private $scope;

    public static $api_key;

    public static $client_id;

    public static $client_secret;

    public static $redirect_uri;

    function __construct($scope = null) {

        $this->session = new \Hazaar\Session();

        $this->scope = $scope;

    }

    public function authenticated() {

        return $this->session->has('access_token');

    }

    public function authenticate($force_approval = false) {

        $params[] = 'response_type=code';

        $params[] = 'client_id=' . OAuth::$client_id;

        $params[] = 'scope=' . $this->scope;

        $params[] = 'redirect_uri=' . OAuth::$redirect_uri;

        if($force_approval)
            $params[] = 'approval_prompt=force';

        header('Location: https://accounts.google.com/o/oauth2/auth?' . implode('&', $params));

        $this->session->request_uri = $_SERVER['REQUEST_URI'];

        exit ;

    }

    public function reset() {

        $this->session->clear();

    }

    public function setClientId($client_id) {

        OAuth::$client_id = $client_id;

    }

    public function setClientSecret($client_secret) {

        OAuth::$client_secret = $client_secret;

    }

    public function redirectUri($uri) {

        OAuth::$redirect_uri = $uri;

    }

    public function exchange($code, $client_id = null, $client_secret = null) {

        $req = new Hazaar_Http_Client('https://accounts.google.com/o/oauth2/token');

        $req->setContentType($req::ENC_FORMDATA);

        $req->setParameter('code', $code);

        $req->setParameter('client_id', OAuth::$client_id);

        $req->setParameter('client_secret', OAuth::$client_secret);

        $req->setParameter('redirect_uri', OAuth::$redirect_uri);

        $req->setParameter('grant_type', 'authorization_code');

        $req->setPost();

        $response = \Hazaar\Json::fromString($req->request());

        if($response->has('access_token')) {

            $this->session->access_token = $response->access_token;

            header('Location: ' . $this->session->request_uri);

        } else {

            echo "<h1>AUTH ERROR</h1>";

            echo "<pre>";

            print_r($response);

            echo "</pre>";

        }

        unset($this->session->request_uri);

        exit ;

    }

    public function getToken() {

        return $this->session->access_token;

    }

}

