<?php

namespace Hazaar\Auth\Adapter;

/**
 * OAuth short summary.
 *
 * OAuth description.
 *
 * @version 1.0
 * @author jamiec
 */
class OAuth2 extends \Hazaar\Auth\Adapter implements _Interface {

    protected $client_id;

    protected $client_secret;

    protected $grant_type = 'code';

    protected $http_client;

    protected $auth_uri;

    protected $token_uri;

    protected $scopes = array();

    function __construct($client_id, $client_secret, $grant_type = 'code', $cache_config = array(), $cache_backend = 'session') {

        parent::__construct($cache_config, $cache_backend);

        $this->http_client = new \Hazaar\Http\Client();

        $this->grant_type = $grant_type;

        $this->client_id = $client_id;

        $this->client_secret = $client_secret;

    }

    public function setAuthURI($uri){

        $this->auth_uri = $uri;

    }

    public function setTokenURI($uri){

        $this->token_uri = $uri;

    }

    public function discover($uri){

        $key = md5($uri);

        if(!$this->session->has('oauth2_metadata'))
            $this->session->oauth2_metadata = array();

        $metadata = $this->session->oauth2_metadata;
            
        if(!array_key_exists($key, $metadata)){

            $metadata[$key] = json_decode(file_get_contents($uri));

            $this->session->oauth2_metadata = $metadata;

        }

        $this->auth_uri = ake($metadata[$key], 'authorization_endpoint');

        $this->token_uri = ake($metadata[$key], 'token_endpoint');

        return true;

    }

    public function addScope(){

        $scopes = func_get_args();

        foreach($scopes as $scope){

            if(is_array($scope))
                $this->addScope($scope);
            else
                $this->scopes[] = $scope;

        }

    }

    /**
     * Check if there is a current user authentication in the session namespace.
     *
     * Returns true if we have an access token.  False otherwise.
     *
     * @return mixed
     */
    public function authenticated() {

        if($this->session->has('oauth2_identity') && $this->session->has('oauth2_data'))
            return (ake($this->session->oauth2_data, 'access_token', '') != '');

        return false;

    }

    /**
     * Authenticate the user credentials using the OAuth2 "password" grant type.
     *
     * @param mixed $identity       The user identity (username).
     *
     * @param mixed $credential     The user credential (password).
     *
     * @param mixed $autologin      The autologin flag.  If checked the session will be remembered and the refresh token used to obtain
     *                              a new access token when it expires.
     * @return boolean              True if the authentication was successful.  False otherwise.
     */
    public function authenticate($identity = NULL, $credential = NULL, $autologin = FALSE){

        if($this->authenticated()){

            if($uri = $this->session->redirect_uri){

                header('Location: ' . $uri);

                unset($this->session->redirect_uri);

                exit;

            }

            return true;

        }

        $data = null;

        switch($this->grant_type){

            case 'password':

                $data = $this->authenticateCredentials($identity, $credential, $this->grant_type);

                break;

            case 'client_credentials':

                $data = $this->authenticateCredentials($identity, $credential, $this->grant_type);

                break;

            case 'code':
            case 'implicit':

                $data = $this->authenticateCode();

                break;

        }

        if($data !== false){

            $this->session->oauth2_data = $data;//, ake($data, 'expires_in'));

            $this->session->oauth2_identity = $identity;//, ake($data, 'expires_in'));

            if($uri = $this->session->redirect_uri){

                header('Location: ' . $uri);

                exit;

            }

            return true;

        }

        return false;

    }

    private function authenticateCredentials($identity, $credential, $grant_type = 'password'){

        $target_url = (is_array($this->target_url) ? ake($this->target_url, 1, ake($this->target_url, 0)) : $this->target_url);

        $request = new \Hazaar\Http\Request($target_url, 'POST');

        $request->grant_type = $grant_type;

        $request->client_id = $this->client_id;

        $request->client_secret = $this->client_secret;

        $request->username = $identity;

        $request->password = $credential;

        $this->http_client->auth($identity, $credential);

        $response = $this->http_client->send($request);

        if($response->status == 200)
            return json_decode($response->body, true);

        return false;

    }

    private function authenticateCode(){

        if(($code = ake($_REQUEST, 'code')) && ake($_REQUEST, 'state') == $this->session->state){

            $request = new \Hazaar\Http\Request($this->token_uri, 'POST');

            $request->client_id = $this->client_id;

            $request->client_secret = $this->client_secret;

            $request->grant_type = 'authorization_code';

            $request->code = $code;

            $request->response_type = 'token';

            if(count($this->scopes))
                $request->scope = implode(' ' , $this->scopes);

            $request->redirect_uri = $this->session->redirect_uri;

            $response = $this->http_client->send($request);

            if($response->status === 200)
                return $response->body();

            return false;

        }elseif(array_key_exists('access_token', $_REQUEST) && ake($_REQUEST, 'state') === $this->session->state){

            return $_REQUEST;

        }elseif(ake($_REQUEST, 'grant_type') == 'implicit'){

            //Some JavaScript magic to turn a hash response into a normal query response.
            $code = 'var uri = document.location.hash.substr(1);
                history.pushState("", document.title, window.location.pathname + window.location.search);
                document.location.search = uri;';

            $view = new \Hazaar\Controller\Response\Html();

            $view->setContent(new \Hazaar\Html\Script($code));

            echo $view->__writeOutput();

            exit;

        }else{

            $this->session->state = md5(uniqid());

            $this->session->redirect_uri = $this->getRedirectUri();

            $params = array(
                'client_id' => $this->client_id,
                'redirect_uri' => rawurlencode($this->session->redirect_uri),
                'state' => $this->session->state
            );

            if($this->grant_type == 'implicit'){

                $params['response_type'] = 'token';

                $params['redirect_uri'] .= '?grant_type=implicit';

            }else{

                $params['response_type'] = 'code';

            }

            if(count($this->scopes) > 0)
                $params['scope'] = implode(' ' , $this->scopes);

            $url = $this->auth_uri . '?' . array_flatten($params, '=', '&');

            header('Location: ' . $url);

            exit;

        }

    }

    public function refresh($token = null, $identity = null, $credential = null){

        if(!$token){

            if(!($token = $this->getRefreshToken()))
                return false;

        }

        $target_url = (is_array($this->target_url) ? ake($this->target_url, 1, ake($this->target_url, 0)) : $this->target_url);

        $request = new \Hazaar\Http\Request($target_url, 'POST');

        $request->grant_type = 'refresh_token';

        $request->client_id = $this->client_id;

        $request->client_secret = $this->client_secret;

        if($identity){

            $request->username = $identity;

            if($credential)
                $request->password = $credential;

            $this->http_client->auth($identity, $credential);

        }

        $response = $this->http_client->send($request);

        if($response->status == 200 && $data = json_decode($response->body, true)){

            $this->session->oauth2_data = $data;

            return true;

        }

        return false;

    }

    private function getRedirectUri(){

        return (array_key_exists('HTTPS', $_SERVER) && boolify($_SERVER['HTTPS']) ? 'https' : 'http' )
            . '://' . $_SERVER['SERVER_NAME']
            . ':' . ake($_SERVER, 'SERVER_PORT', 80)
            . $_SERVER['REQUEST_URI'];

    }

    public function getAccessToken() {

        if($this->has('oauth2_data'))
            return ake($this->session->oauth2_data, 'access_token');

        return false;

    }

    public function getRefreshToken(){

        if($this->has('oauth2_data'))
            return ake($this->session->oauth2_data, 'refresh_token', false);

        return false;

    }

    public function queryAuth($identity, $extra = array()){

        return false;

    }

    public function getToken(){

        return ake($this->session->oauth2_data, 'access_token');

    }

    public function getTokenType(){

        return ake($this->session->oauth2_data, 'token_type', 'Bearer');

    }

}
