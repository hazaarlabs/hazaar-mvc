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

    public function setLogoutURI(string $uri): void
    {
        $this->metadata['end_session_endpoint'] = $uri;
    }

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
