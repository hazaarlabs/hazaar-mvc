<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Application;
use Hazaar\Application\URL;
use Hazaar\Auth\Interfaces\Adapter;
use Hazaar\Cache;
use Hazaar\Controller\Response\HTML;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;
use Hazaar\Map;

class OAuth2 extends Session implements Adapter
{
    protected string $client_id;
    protected string $client_secret;
    protected string $grant_type = 'code';
    protected Client $http_client;

    /**
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * @var array<string>
     */
    protected array $scopes = [];
    private \Closure $authenticate_callback;

    /**
     * @param array<mixed>|Map $cacheConfig
     */
    public function __construct(
        string $client_id,
        string $client_secret,
        string $grant_type = 'code',
        array|Map $cacheConfig = [],
        ?Cache $cacheBackend = null
    ) {
        parent::__construct($cacheConfig, $cacheBackend);
        $this->http_client = new Client();
        $this->http_client->authorisation($this);
        $this->grant_type = $grant_type;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        if ($config = Application::getInstance()->config['auth']) {
            $this->metadata = $config;
            if (isset($this->metadata['discover_endpoint'])) {
                $this->discover($this->metadata['discover_endpoint']);
            }
        }
    }

    public function setAuthURI(string $uri): void
    {
        $this->metadata['authorization_endpoint'] = $uri;
    }

    public function setTokenURI(string $uri): void
    {
        $this->metadata['token_endpoint'] = $uri;
    }

    public function setRegistrationURI(string $uri): void
    {
        $this->metadata['registration_endpoint'] = $uri;
    }

    public function setIntrospectURI(string $uri): void
    {
        $this->metadata['introspection_endpoint'] = $uri;
    }

    public function setRevokeURI(string $uri): void
    {
        $this->metadata['revocation_endpoint'] = $uri;
    }

    public function setUserinfoURI(string $uri): void
    {
        $this->metadata['user_info_endpoint'] = $uri;
    }

    public function setAuthenticateCallback(\Closure $cb): void
    {
        $this->authenticate_callback = $cb;
    }

    public function discover(string $uri): bool
    {
        $key = hash('sha1', $uri);
        if (!$this->session->has('oauth2_metadata')) {
            $this->session['oauth2_metadata'] = [];
        }
        $metadata = $this->session['oauth2_metadata'];
        if (!(array_key_exists($key, $metadata) && $metadata[$key])) {
            if (!($meta_source = @file_get_contents($uri))) {
                throw new \Exception('Authentication platform offline.  Service discovery failed!');
            }
            $metadata[$key] = json_decode($meta_source, true);
            $this->session['oauth2_metadata'] = $metadata;
        }
        $this->metadata = $metadata[$key];

        return true;
    }

    public function addScope(): void
    {
        $scopes = func_get_args();
        foreach ($scopes as $scope) {
            if (is_array($scope)) {
                $this->addScope($scope);
            } else {
                $this->scopes[] = $scope;
            }
        }
    }

    public function hasScope(string $key): bool
    {
        return $this->authenticated() && in_array($key, $this->scopes);
    }

    /**
     * Get the current scopes for this OAuth2 adapter.
     *
     * @return array<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Check if there is a current user authentication in the session namespace.
     *
     * Returns true if we have an access token.  False otherwise.
     */
    public function authenticated(): bool
    {
        if ($this->session->has('oauth2_data')
            && ($this->session->has('oauth2_expiry') && $this->session['oauth2_expiry'] > time())) {
            return '' !== ake($this->session['oauth2_data'], 'access_token', '');
        }
        if ($refresh_token = ake($this->session['oauth2_data'], 'refresh_token')) {
            return $this->refresh($refresh_token);
        }

        return false;
    }

    /**
     * Authenticate the user credentials using the OAuth2 "password" grant type.
     *
     * @param string $identity   the user identity (username)
     * @param string $credential the user credential (password)
     * @param bool   $autologin  The autologin flag.  If checked the session will be remembered and the refresh token used to obtain
     *                           a new access token when it expires.
     *
     * @return bool True if the authentication was successful.  False otherwise.
     */
    public function authenticate(?string $identity = null, ?string $credential = null, bool $autologin = false, bool $skip_auth_check = false): bool
    {
        if (true !== $skip_auth_check && $this->authenticated()) {
            if ($uri = $this->session['redirect_uri']) {
                header('Location: '.$uri);
                unset($this->session['redirect_uri']);

                exit;
            }

            return true;
        }
        $data = null;

        switch ($this->grant_type) {
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
        if (false !== $this->authorize($data)) {
            // Set the standard hazaar auth session details for compatibility
            $this->session['hazaar_auth_identity'] = $data->access_token;
            $this->session['hazaar_auth_token'] = hash($this->options['token']['hash'], $this->getIdentifier($this->session['hazaar_auth_identity']));
            if (is_callable($this->authenticate_callback)) {
                call_user_func($this->authenticate_callback, $data);
            }
            if ($uri = $this->session['redirect_uri']) {
                header('Location: '.$uri);
                unset($this->session->redirect_uri);

                exit;
            }

            return true;
        }

        return false;
    }

    public function refresh(?string $token = null, ?string $identity = null, ?string $credential = null): bool
    {
        if (!$token) {
            if (!($token = $this->getRefreshToken())) {
                return false;
            }
        }
        if (!($uri = ake($this->metadata, 'token_endpoint'))) {
            throw new \Exception('There is no token endpoint set for this auth adapter!');
        }
        $request = new Request($uri, 'POST');
        $request['grant_type'] = 'refresh_token';
        $request['client_id'] = $this->client_id;
        $request['client_secret'] = $this->client_secret;
        $request['refresh_token'] = $token;
        if ($identity) {
            $request['username'] = $identity;
            if ($credential) {
                $request['password'] = $credential;
            }
            $this->http_client->auth($identity, $credential);
        }
        $response = $this->http_client->send($request);
        if (200 == $response->status && $data = json_decode($response->body)) {
            if (false !== $this->authorize($data)) {
                $this->session['hazaar_auth_identity'] = $data->access_token;
                $this->session['hazaar_auth_token'] = hash($this->options['token']['hash'], $this->getIdentifier($this->session['hazaar_auth_identity']));

                return true;
            }
        }

        return false;
    }

    public function getAccessToken(): bool|string
    {
        if ($this->has('oauth2_data')) {
            return ake($this->session['oauth2_data'], 'access_token');
        }

        return false;
    }

    public function getRefreshToken(): bool|string
    {
        if ($this->has('oauth2_data')) {
            return ake($this->session['oauth2_data'], 'refresh_token', false);
        }

        return false;
    }

    /**
     * @param array<mixed> $extra
     *
     * @return array<mixed>|bool
     */
    public function queryAuth(string $identity, array $extra = []): array|bool
    {
        return false;
    }

    public function getToken(): string
    {
        return ake($this->session['oauth2_data'], 'access_token');
    }

    public function getTokenType(): string
    {
        return ake($this->session['oauth2_data'], 'token_type', 'Bearer');
    }

    public function introspect(?string $token = null, string $token_type = 'access_token'): bool|string
    {
        if (!($uri = ake($this->metadata, 'introspection_endpoint'))) {
            return false;
        }
        $request = new Request($uri, 'POST');
        $request['client_id'] = $this->client_id;
        $request['client_secret'] = $this->client_secret;
        $request['token = $token'] ? $token : ake($this->session['oauth2_data'], 'access_token');
        $request['token_type_hint'] = $token_type;
        $response = $this->http_client->send($request);

        return $response->body();
    }

    public function revoke(): bool|string
    {
        if (!($uri = ake($this->metadata, 'revocation_endpoint'))) {
            return false;
        }
        $request = new Request($uri, 'POST');
        $request['client_id'] = $this->client_id;
        $request['token_type_hint'] = 'access_token';
        $request['token'] = ake($this->session['oauth2_data'], 'access_token');
        $response = $this->http_client->send($request);

        return ake($response->body(), 'result', false);
    }

    public function userinfo(): bool|string
    {
        if (!($uri = ake($this->metadata, 'user_info_endpoint'))) {
            return false;
        }
        $request = new Request($uri, 'GET');
        $response = $this->http_client->send($request);
        if (200 !== $response->status) {
            return false;
        }

        return $response->body();
    }

    /**
     * Authorize the OAuth2 data.
     */
    private function authorize(\stdClass $data): bool
    {
        if (!((property_exists($data, 'token_type') && 'bearer' === strtolower($data->token_type))
            && \property_exists($data, 'access_token')
            && \property_exists($data, 'expires_in'))) {
            return false;
        }
        $this->session['oauth2_expiry'] = time() + ake($data, 'expires_in');
        $this->session['oauth2_data'] = $data;

        return true;
    }

    private function authenticateCredentials(
        string $identity,
        string $credential,
        string $grant_type = 'password',
        ?string $scope = null
    ): bool|\stdClass {
        if (!($token_endpoint = ake($this->metadata, 'token_endpoint'))) {
            return false;
        }
        $target_url = (is_array($token_endpoint) ? ake($token_endpoint, 1, ake($token_endpoint, 0)) : $token_endpoint);
        $request = new Request($target_url, 'POST');
        $request['grant_type'] = $grant_type;
        $request['client_id'] = $this->client_id;
        $request['client_secret'] = $this->client_secret;
        if (null !== $identity) {
            $request['username'] = $identity;
            $request['password'] = $credential;
            $this->http_client->auth($identity, $credential);
        }
        if (count($this->scopes)) {
            $request['scope'] = implode(' ', $this->scopes);
        }
        $response = $this->http_client->send($request);
        if (200 == $response->status) {
            return json_decode($response->body);
        }

        return false;
    }

    private function authenticateCode(): bool|\stdClass
    {
        if ($code = ake($_REQUEST, 'code')) {
            if (ake($_REQUEST, 'state') !== $this->session['state']) {
                throw new \Exception('Invalid state code', 400);
            }
            $request = new Request(ake($this->metadata, 'token_endpoint'), 'POST');
            $request['client_id'] = $this->client_id;
            $request['client_secret'] = $this->client_secret;
            $request['grant_type'] = 'authorization_code';
            $request['code'] = $code;
            $request['response_type'] = 'token';
            if (count($this->scopes)) {
                $request['scope'] = implode(' ', $this->scopes);
            }
            $request['redirect_uri'] = $this->session['redirect_uri'];
            $response = $this->http_client->send($request);
            if (200 === $response->status) {
                return $response->body();
            }

            return false;
        }
        if (array_key_exists('access_token', $_REQUEST) && ake($_REQUEST, 'state') === $this->session['state']) {
            return array_to_object($_REQUEST);
        }
        if ('implicit' == ake($_REQUEST, 'grant_type')) {
            // Some JavaScript magic to turn a hash response into a normal query response.
            $code = 'var uri = document.location.hash.substr(1);
                history.pushState("", document.title, window.location.pathname + window.location.search);
                document.location.search = uri;';
            $view = new HTML();
            $view->setContent("<script>{$code}</script>");
            $view->__writeOutput();

            exit;
        }
        if (!ake($this->metadata, 'authorization_endpoint')) {
            throw new \Exception('There is no authorization endpoint set for this auth adapter!');
        }
        $this->session['state'] = hash('sha1', uniqid());
        $this->session['redirect_uri'] = $this->getRedirectUri();
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => rawurlencode($this->session['redirect_uri']),
            'state' => $this->session['state'],
        ];
        if ('implicit' == $this->grant_type) {
            $params['response_type'] = 'token';
            $params['redirect_uri'] .= '?grant_type=implicit';
        } else {
            $params['response_type'] = 'code';
        }
        if (count($this->scopes) > 0) {
            $params['scope'] = implode(' ', $this->scopes);
        }
        $url = ake($this->metadata, 'authorization_endpoint').'?'.array_flatten($params, '=', '&');
        header('Location: '.$url);

        exit;
    }

    private function getRedirectUri(): string
    {
        if (APPLICATION_BASE !== substr($_SERVER['REQUEST_URI'], 0, strlen(APPLICATION_BASE))) {
            throw new \Exception('The current APPLICATION_BASE does not match the REQUEST_URI?  What the!?');
        }
        $action = $_SERVER['REQUEST_URI'];
        if (APPLICATION_BASE !== '/') {
            $action = trim(str_replace(addslashes(APPLICATION_BASE), '', $_SERVER['REQUEST_URI']), '/');
        }
        $url = new URL($action);

        return (string) $url;
    }
}
