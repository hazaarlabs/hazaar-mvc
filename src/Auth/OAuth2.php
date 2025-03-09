<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Application;
use Hazaar\Application\URL;
use Hazaar\Arr;
use Hazaar\Auth\Adapter;
use Hazaar\Controller\Response\HTML;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;

class OAuth2 extends Adapter
{
    protected string $clientID;
    protected string $clientSecret;
    protected string $grantType = 'code';
    protected Client $httpClient;

    /**
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * @var array<string>
     */
    protected array $scopes = [];
    private ?\Closure $authenticateCallback = null;

    public function __construct(
        string $clientID,
        string $clientSecret,
        string $grantType = 'code',
        array $config = []
    ) {
        parent::__construct($config);
        $this->httpClient = new Client();
        $this->httpClient->authorisation($this);
        $this->grantType = $grantType;
        $this->clientID = $clientID;
        $this->clientSecret = $clientSecret;
        if ($config = Application::getInstance()->config['auth']) {
            $this->metadata = $config;
            if (isset($this->metadata['discover_endpoint'])) {
                $this->discover($this->metadata['discover_endpoint']);
            }
        }
    }

    /**
     * Sets the authorization endpoint URI for OAuth2 authentication.
     *
     * @param string $uri the URI of the authorization endpoint
     */
    public function setAuthURI(string $uri): void
    {
        $this->metadata['authorization_endpoint'] = $uri;
    }

    /**
     * Sets the URI for the token endpoint.
     *
     * @param string $uri the URI to be set for the token endpoint
     */
    public function setTokenURI(string $uri): void
    {
        $this->metadata['token_endpoint'] = $uri;
    }

    /**
     * Sets the registration URI for the OAuth2 metadata.
     *
     * This method updates the 'registration_endpoint' key in the metadata array
     * with the provided URI.
     *
     * @param string $uri the registration URI to be set
     */
    public function setRegistrationURI(string $uri): void
    {
        $this->metadata['registration_endpoint'] = $uri;
    }

    /**
     * Sets the URI for the introspection endpoint.
     *
     * This method updates the metadata to include the provided URI for the
     * introspection endpoint, which is used to validate access tokens.
     *
     * @param string $uri the URI of the introspection endpoint
     */
    public function setIntrospectURI(string $uri): void
    {
        $this->metadata['introspection_endpoint'] = $uri;
    }

    /**
     * Sets the URI for the revocation endpoint.
     *
     * This method allows you to specify the URI that will be used to revoke OAuth2 tokens.
     *
     * @param string $uri the URI of the revocation endpoint
     */
    public function setRevokeURI(string $uri): void
    {
        $this->metadata['revocation_endpoint'] = $uri;
    }

    /**
     * Sets the URI for the user info endpoint in the OAuth2 metadata.
     *
     * @param string $uri the URI of the user info endpoint
     */
    public function setUserinfoURI(string $uri): void
    {
        $this->metadata['user_info_endpoint'] = $uri;
    }

    /**
     * Sets the callback function to be used for authentication.
     *
     * @param \Closure $cb the callback function to be used for authentication
     */
    public function setAuthenticateCallback(\Closure $cb): void
    {
        $this->authenticateCallback = $cb;
    }

    /**
     * Discover OAuth2 metadata from a given URI.
     *
     * This method attempts to retrieve and cache OAuth2 metadata from the specified URI.
     * If the metadata is not already cached, it fetches the metadata from the URI and stores it.
     * If the URI cannot be accessed, an exception is thrown.
     *
     * @param string $uri the URI to discover OAuth2 metadata from
     *
     * @return bool returns true on successful discovery
     *
     * @throws \Exception if the authentication platform is offline or service discovery fails
     */
    public function discover(string $uri): bool
    {
        $key = hash('sha1', $uri);
        if (!$this->storage->has('oauth2_metadata')) {
            $this->storage->set('oauth2_metadata', []);
        }
        $metadata = $this->storage->get('oauth2_metadata');
        if (!(array_key_exists($key, $metadata) && $metadata[$key])) {
            if (!($meta_source = @file_get_contents($uri))) {
                throw new \Exception('Authentication platform offline.  Service discovery failed!');
            }
            $metadata[$key] = json_decode($meta_source, true);
            $this->storage->set('oauth2_metadata', $metadata);
        }
        $this->metadata = $metadata[$key];

        return true;
    }

    /**
     * Adds one or more scopes to the OAuth2 authorization.
     *
     * This method accepts a variable number of arguments. Each argument can be a string representing a single scope
     * or an array of scopes. If an array is provided, the method will recursively add each scope in the array.
     */
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

    /**
     * Checks if the authenticated user has a specific scope.
     *
     * This method verifies if the user is authenticated and if the specified
     * scope key exists within the user's scopes.
     *
     * @param string $key the scope key to check
     *
     * @return bool returns true if the user is authenticated and has the specified scope, false otherwise
     */
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
        if ($this->storage->has('oauth2_data')
            && ($this->storage->has('oauth2_expiry') && $this->storage->get('oauth2_expiry') > time())) {
            return ($this->storage->get('oauth2_data')['access_token'] ?? '') !== '';
        }
        if ($refresh_token = ($this->storage->get('oauth2_data')['refresh_token'] ?? null)) {
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
    public function authenticate(
        ?string $identity = null,
        ?string $credential = null,
        bool $autologin = false,
        bool $skip_auth_check = false
    ): bool {
        if (true !== $skip_auth_check && $this->authenticated()) {
            if ($uri = $this->storage->get('redirect_uri')) {
                header('Location: '.$uri);
                $this->storage->unset('redirect_uri');

                exit;
            }

            return true;
        }
        $data = null;

        switch ($this->grantType) {
            case 'password':
                $data = $this->authenticateCredentials($identity, $credential, $this->grantType);

                break;

            case 'client_credentials':
                $data = $this->authenticateCredentials($identity, $credential, $this->grantType);

                break;

            case 'code':
            case 'implicit':
                $data = $this->authenticateCode();

                break;
        }
        if (false !== $this->authorize($data)) {
            // Set the standard hazaar auth session details for compatibility
            $this->storage->set('hazaar_auth_identity', $data->access_token);
            $this->storage->set('hazaar_auth_token', hash($this->storage['token']['hash'], $this->getIdentifier($this->storage->get('hazaar_auth_identity'))));
            if (is_callable($this->authenticateCallback)) {
                call_user_func($this->authenticateCallback, $data);
            }
            if ($uri = $this->storage->get('redirect_uri')) {
                header('Location: '.$uri);
                $this->storage->unset('redirect_uri');

                exit;
            }

            return true;
        }

        return false;
    }

    /**
     * Refreshes the OAuth2 token.
     *
     * This method attempts to refresh the OAuth2 token using the provided refresh token,
     * identity, and credential. If no token is provided, it will attempt to retrieve a
     * refresh token using the `getRefreshToken` method. The method sends a POST request
     * to the token endpoint with the necessary parameters to obtain a new access token.
     *
     * @param null|string $token      the refresh token to use for refreshing the access token
     * @param null|string $identity   the identity (username) to use for authentication
     * @param null|string $credential the credential (password) to use for authentication
     *
     * @return bool returns true if the token was successfully refreshed and authorized,
     *              false otherwise
     *
     * @throws \Exception if there is no token endpoint set for this auth adapter
     */
    public function refresh(?string $token = null, ?string $identity = null, ?string $credential = null): bool
    {
        if (!$token) {
            if (!($token = $this->getRefreshToken())) {
                return false;
            }
        }
        if (!($uri = ($this->metadata['token_endpoint'] ?? null))) {
            throw new \Exception('There is no token endpoint set for this auth adapter!');
        }
        $request = new Request($uri, 'POST');
        $request['grantType'] = 'refresh_token';
        $request['clientID'] = $this->clientID;
        $request['clientSecret'] = $this->clientSecret;
        $request['refresh_token'] = $token;
        if ($identity) {
            $request['username'] = $identity;
            if ($credential) {
                $request['password'] = $credential;
            }
            $this->httpClient->auth($identity, $credential);
        }
        $response = $this->httpClient->send($request);
        if (200 == $response->status && $data = json_decode($response->body)) {
            if (false !== $this->authorize($data)) {
                $this->storage['hazaar_auth_identity'] = $data->access_token;
                $this->storage['hazaar_auth_token'] = hash($this->options['token']['hash'], $this->getIdentifier($this->storage['hazaar_auth_identity']));

                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves the OAuth2 access token.
     *
     * This method checks if the 'oauth2_data' key exists in the storage.
     * If it exists, it returns the 'access_token' from the 'oauth2_data'.
     * Otherwise, it returns false.
     *
     * @return bool|string the access token if available, otherwise false
     */
    public function getAccessToken(): bool|string
    {
        if ($this->has('oauth2_data')) {
            return $this->storage['oauth2_data']['access_token'] ?? false;
        }

        return false;
    }

    /**
     * Retrieves the refresh token from the OAuth2 data storage.
     *
     * This method checks if the 'oauth2_data' key exists in the storage. If it does,
     * it attempts to retrieve the 'refresh_token' from the 'oauth2_data' array.
     * If the 'refresh_token' is not found, it returns false.
     *
     * @return bool|string returns the refresh token if available, otherwise false
     */
    public function getRefreshToken(): bool|string
    {
        if ($this->has('oauth2_data')) {
            return $this->storage['oauth2_data']['refresh_token'] ?? false;
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

    /**
     * Retrieves the OAuth2 token from the storage.
     *
     * This method returns an associative array containing the 'access_token'
     * from the 'oauth2_data' stored in the class. If the 'access_token' is not
     * present, it returns null.
     *
     * @return null|array{token:string} an associative array with the 'access_token' or null if not found
     */
    public function getToken(): ?array
    {
        return ['token' => $this->storage['oauth2_data']['access_token'] ?? null];
    }

    /**
     * Retrieves the token type from the OAuth2 data storage.
     *
     * This method accesses the 'oauth2_data' array within the storage and returns the value
     * associated with the 'token_type' key. If the 'token_type' key does not exist, it defaults
     * to returning 'Bearer'.
     *
     * @return string the token type, defaulting to 'Bearer' if not specified
     */
    public function getTokenType(): string
    {
        return $this->storage['oauth2_data']['token_type'] ?? 'Bearer';
    }

    /**
     * Introspects the given token using the OAuth2 introspection endpoint.
     *
     * @param null|string $token      The token to introspect. If null, the access token from storage will be used.
     * @param string      $token_type the type of the token, default is 'access_token'
     *
     * @return bool|string the response body from the introspection endpoint, or false if the endpoint is not available
     */
    public function introspect(?string $token = null, string $token_type = 'access_token'): bool|string
    {
        if (!($uri = $this->metadata['introspection_endpoint'] ?? null)) {
            return false;
        }
        $request = new Request($uri, 'POST');
        $request['clientID'] = $this->clientID;
        $request['clientSecret'] = $this->clientSecret;
        $request['token'] = $token ?? ($this->storage['oauth2_data']['access_token'] ?? null);
        $request['token_type_hint'] = $token_type;
        $response = $this->httpClient->send($request);

        return $response->body();
    }

    /**
     * Revokes the OAuth2 access token.
     *
     * This method sends a revocation request to the OAuth2 server's revocation endpoint.
     * It constructs a POST request with the client ID and access token, and sends it
     * using the HTTP client. The response from the server is then checked for a result.
     *
     * @return bool|string Returns false if the revocation endpoint is not found or if the revocation fails.
     *                     Returns the result from the server's response body if the revocation is successful.
     */
    public function revoke(): bool|string
    {
        if (!($uri = $this->metadata['revocation_endpoint'] ?? null)) {
            return false;
        }
        $request = new Request($uri, 'POST');
        $request['clientID'] = $this->clientID;
        $request['token_type_hint'] = 'access_token';
        $request['token'] = $this->storage['oauth2_data']['access_token'] ?? null;
        $response = $this->httpClient->send($request);

        return $response->body()['result'] ?? false;
    }

    /**
     * Retrieves user information from the OAuth2 user info endpoint.
     *
     * This method sends a GET request to the user info endpoint specified in the metadata.
     * If the endpoint is not available or the request fails, it returns false.
     * Otherwise, it returns the response body containing the user information.
     *
     * @return bool|string returns the user information as a string on success, or false on failure
     */
    public function userinfo(): bool|string
    {
        if (!($uri = $this->metadata['user_info_endpoint'] ?? null)) {
            return false;
        }
        $request = new Request($uri, 'GET');
        $response = $this->httpClient->send($request);
        if (200 !== $response->status) {
            return false;
        }

        return $response->body();
    }

    /**
     * Authorizes the OAuth2 data by validating the required properties and storing the data.
     *
     * @param \stdClass $data the OAuth2 data object containing token information
     *
     * @return bool returns true if the data contains valid OAuth2 token information, otherwise false
     */
    private function authorize(\stdClass $data): bool
    {
        if (!((property_exists($data, 'token_type') && 'bearer' === strtolower($data->token_type))
            && \property_exists($data, 'access_token')
            && \property_exists($data, 'expires_in'))) {
            return false;
        }
        $this->storage['oauth2_expiry'] = time() + ($data->expires_in ?? 0);
        $this->storage['oauth2_data'] = $data;

        return true;
    }

    /**
     * Authenticates user credentials against an OAuth2 token endpoint.
     *
     * @param string      $identity   The user's identity (e.g., username or email).
     * @param string      $credential The user's credential (e.g., password).
     * @param string      $grantType  The type of grant being requested. Defaults to 'password'.
     * @param null|string $scope      optional scope of the access request
     *
     * @return bool|\stdClass returns a stdClass object containing the token information if authentication is successful, or false otherwise
     */
    private function authenticateCredentials(
        string $identity,
        string $credential,
        string $grantType = 'password',
        ?string $scope = null
    ): bool|\stdClass {
        if (!($token_endpoint = $this->metadata['token_endpoint'] ?? null)) {
            return false;
        }
        $target_url = (is_array($token_endpoint) ? ($token_endpoint[1] ?? $token_endpoint[0] ?? null) : $token_endpoint);
        $request = new Request($target_url, 'POST');
        $request['grantType'] = $grantType;
        $request['clientID'] = $this->clientID;
        $request['clientSecret'] = $this->clientSecret;
        if ('' !== trim($identity)) {
            $request['username'] = $identity;
            $request['password'] = $credential;
            $this->httpClient->auth($identity, $credential);
        }
        if (count($this->scopes)) {
            $request['scope'] = implode(' ', $this->scopes);
        }
        $response = $this->httpClient->send($request);
        if (200 == $response->status) {
            return json_decode($response->body);
        }

        return false;
    }

    /**
     * Authenticates the user using the OAuth2 authorization code flow.
     *
     * This method handles the OAuth2 authorization code flow by performing the following steps:
     * 1. Checks if an authorization code is present in the request.
     * 2. Validates the state parameter to prevent CSRF attacks.
     * 3. Sends a request to the token endpoint to exchange the authorization code for an access token.
     * 4. Handles the implicit grant type by converting the hash response to a query response.
     * 5. Redirects the user to the authorization endpoint if no authorization code or access token is present.
     *
     * @return bool|\stdClass returns the response body as an object if the authentication is successful,
     *                        or false if the authentication fails
     *
     * @throws \Exception if the state code is invalid or if there is no authorization endpoint set
     */
    private function authenticateCode(): bool|\stdClass
    {
        if ($code = ($_REQUEST['code'] ?? null)) {
            if (($_REQUEST['state'] ?? null) !== $this->storage['state']) {
                throw new \Exception('Invalid state code', 400);
            }
            $request = new Request($this->metadata['token_endpoint'] ?? null, 'POST');
            $request['clientID'] = $this->clientID;
            $request['clientSecret'] = $this->clientSecret;
            $request['grantType'] = 'authorization_code';
            $request['code'] = $code;
            $request['response_type'] = 'token';
            if (count($this->scopes)) {
                $request['scope'] = implode(' ', $this->scopes);
            }
            $request['redirect_uri'] = $this->storage['redirect_uri'];
            $response = $this->httpClient->send($request);
            if (200 === $response->status) {
                return $response->body();
            }

            return false;
        }
        if (array_key_exists('access_token', $_REQUEST) && ($_REQUEST['state'] ?? null) === $this->storage['state']) {
            return Arr::toObject($_REQUEST);
        }
        if ('implicit' == ($_REQUEST['grantType'] ?? null)) {
            // Some JavaScript magic to turn a hash response into a normal query response.
            $code = 'var uri = document.location.hash.substr(1);
                history.pushState("", document.title, window.location.pathname + window.location.search);
                document.location.search = uri;';
            $view = new HTML();
            $view->setContent("<script>{$code}</script>");
            $view->writeOutput();

            exit;
        }
        if (!($this->metadata['authorization_endpoint'] ?? null)) {
            throw new \Exception('There is no authorization endpoint set for this auth adapter!');
        }
        $this->storage['state'] = hash('sha1', uniqid());
        $this->storage['redirect_uri'] = $this->getRedirectUri();
        $params = [
            'clientID' => $this->clientID,
            'redirect_uri' => rawurlencode($this->storage['redirect_uri']),
            'state' => $this->storage['state'],
        ];
        if ('implicit' == $this->grantType) {
            $params['response_type'] = 'token';
            $params['redirect_uri'] .= '?grantType=implicit';
        } else {
            $params['response_type'] = 'code';
        }
        if (count($this->scopes) > 0) {
            $params['scope'] = implode(' ', $this->scopes);
        }
        $url = $this->metadata['authorization_endpoint'].'?'.Arr::flatten($params, '=', '&');
        header('Location: '.$url);

        exit;
    }

    /**
     * Generates the redirect URI based on the current request URI.
     *
     * @return string the generated redirect URI
     *
     * @throws \Exception if the current APPLICATION_BASE does not match the REQUEST_URI
     */
    private function getRedirectUri(): string
    {
        $app = Application::getInstance();
        if (!$app) {
            throw new \Exception('No application instance found!');
        }
        $applicationBase = $app->getBase();
        if ($applicationBase !== substr($_SERVER['REQUEST_URI'], 0, strlen($applicationBase))) {
            throw new \Exception('The current application base does not match the REQUEST_URI?  What the!?');
        }
        $action = $_SERVER['REQUEST_URI'];
        $url = new URL($action);

        return (string) $url;
    }
}
