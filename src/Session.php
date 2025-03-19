<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Session.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

use Hazaar\Cache\Adapter;

/**
 * @brief       Session class
 *
 * @detail      Sessions make use of the Hazaar\Cache class but they create a unique session ID so stored data is not
 *              shared between user sessions.  If you want to store data that can be shared then use the Hazaar\Cache
 *              classes directly.
 */
class Session extends Adapter
{
    private string $sessionName = 'hazaar-session';
    private ?string $sessionId = null;
    private bool $sessionInit = false;

    /**
     * @param array<mixed> $options
     */
    public function __construct(array $options = [], ?string $backend = null)
    {
        $options = array_merge([
            'hash_algorithm' => 'ripemd128',
            'session_name' => 'hazaar-session',
        ], $options);
        if (isset($options['session_name'])) {
            $this->sessionName = $options['session_name'];
        }
        if (isset($options['session_id'])) {
            $this->sessionId = $options['session_id'];
        }
        if (!($this->sessionId || ($this->sessionId = ($_COOKIE[$this->sessionName] ?? null)))) {
            $this->sessionId = null !== $options['session_id'] ? $options['session_id'] : hash($options['hash_algorithm'], uniqid());
        } else {
            $this->sessionInit = true;
        }
        $options['use_pragma'] = false;
        $options['keepalive'] = true;
        parent::__construct($backend, $options, $this->sessionId);
        if (!$this->backend->can('keepalive')) {
            throw new \Exception('The currently selected cache backend, '.get_class($this->backend).', does not support the keepalive feature which is required by the '.__CLASS__.' class.  Please choose a caching backend that supports the keepalive feature.');
        }
    }

    public function set(mixed $key, mixed $value, int $timeout = 0): bool
    {
        if (true !== $this->sessionInit && false === strpos(php_sapi_name(), 'cli')) {
            setcookie($this->sessionName, $this->sessionId, 0, Application::getPath());
            $this->sessionInit = true;
        }

        return parent::set($key, $value, $timeout);
    }

    public function clear(): void
    {
        parent::clear();
        if (($_COOKIE[$this->sessionName] ?? null) === $this->sessionId) {
            setcookie($this->sessionName, '', time() - 3600, Application::getPath());
        }
    }
}
