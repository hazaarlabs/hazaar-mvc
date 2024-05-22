<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Cache;
use Hazaar\HTTP\URL;

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
    /**
     * @param array<mixed> $cacheConfig
     */
    public function __construct(
        string $client_id,
        string $client_secret,
        string $grant_type = 'code',
        array $cacheConfig = [],
        Cache $cacheBackend = null
    ) {
        parent::__construct($client_id, $client_secret, $grant_type, $cacheConfig, $cacheBackend);
        $this->addScope('openid');
    }

    public function setLogoutURI(string $uri): void
    {
        $this->metadata['end_session_endpoint'] = $uri;
    }

    public function getProfile(): ?\stdClass
    {
        if (!$this->session->has('oauth2_data')) {
            return null;
        }
        if (!($this->session['oauth2_data'] instanceof \stdClass && property_exists($this->session['oauth2_data'], 'id_token'))) {
            return null;
        }
        $parts = explode('.', $this->session['oauth2_data']['id_token']);
        foreach ($parts as &$part) {
            $part = \base64url_decode($part);
        }

        return json_decode($parts[1]);
    }

    public function logout(string $redirect_url = null): bool|URL
    {
        if (!($url = ake($this->metadata, 'end_session_endpoint'))) {
            return false;
        }
        $endpoint = new URL($url);
        $endpoint['client_id'] = $this->client_id;
        $endpoint['id_token_hint'] = ake($this->session['oauth2_data'], 'id_token');
        if ($redirect_url) {
            $endpoint['post_logout_redirect_uri'] = (string) $redirect_url;
        }
        if (!$this->deauth()) {
            return false;
        }

        return $endpoint;
    }
}
