<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\HTTP\URL;
use Hazaar\Map;

/**
 * OAuth short summary.
 *
 * OAuth description.
 *
 * @version 1.0
 *
 * @author jamiec
 */
class OpenID extends OAuth2
{
    public function __construct(
        string $clientID,
        string $clientSecret,
        string $grantType = 'code',
        ?Map $config = null,
    ) {
        parent::__construct($clientID, $clientSecret, $grantType, $config);
        $this->addScope('openid');
    }

    /**
     * Sets the URI for the logout endpoint.
     *
     * This method updates the metadata to include the specified URI as the
     * endpoint for ending a session.
     *
     * @param string $uri the URI to be set as the logout endpoint
     */
    public function setLogoutURI(string $uri): void
    {
        $this->metadata['end_session_endpoint'] = $uri;
    }

    /**
     * Retrieves the user profile from the OAuth2 data stored in the session.
     *
     * This method checks if the 'oauth2_data' is present in the storage and if it is an instance of \stdClass
     * with a property 'id_token'. If these conditions are met, it decodes the 'id_token' and returns the
     * decoded JSON object representing the user profile.
     *
     * @return null|\stdClass the user profile as a \stdClass object, or null if the profile cannot be retrieved
     */
    public function getProfile(): ?\stdClass
    {
        if (!$this->storage->has('oauth2_data')) {
            return null;
        }
        if (!($this->storage['oauth2_data'] instanceof \stdClass && property_exists($this->storage['oauth2_data'], 'id_token'))) {
            return null;
        }
        $parts = explode('.', $this->storage['oauth2_data']['id_token']);
        foreach ($parts as &$part) {
            $part = \base64url_decode($part);
        }

        return json_decode($parts[1]);
    }

    /**
     * Logs out the user by clearing the session and redirecting to the OpenID end session endpoint.
     *
     * @param null|string $redirect_url optional URL to redirect to after logout
     *
     * @return bool|URL returns the URL object for the end session endpoint if successful, or false on failure
     */
    public function logout(?string $redirect_url = null): bool|URL
    {
        if (!($url = ake($this->metadata, 'end_session_endpoint'))) {
            return false;
        }
        $endpoint = new URL($url);
        $endpoint['clientID'] = $this->clientID;
        $endpoint['id_token_hint'] = ake($this->storage['oauth2_data'], 'id_token');
        if ($redirect_url) {
            $endpoint['post_logout_redirect_uri'] = (string) $redirect_url;
        }
        if (!$this->clear()) {
            return false;
        }

        return $endpoint;
    }
}
