<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Session.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

/**
 * @brief       Session class
 *
 * @detail      Sessions make use of the Hazaar\Cache class but they create a unique session ID so stored data is not
 *              shared between user sessions.  If you want to store data that can be shared then use the Hazaar\Cache
 *              classes directly.
 */
class Session extends Cache
{
    private string $session_name = 'hazaar-session';
    private ?string $session_id = null;
    private bool $session_init = false;

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
            $this->session_name = $options['session_name'];
        }
        if (isset($options['session_id'])) {
            $this->session_id = $options['session_id'];
        }
        if (!($this->session_id || ($this->session_id = ake($_COOKIE, $this->session_name)))) {
            $this->session_id = null !== $options['session_id'] ? $options['session_id'] : hash($options['hash_algorithm'], uniqid());
        } else {
            $this->session_init = true;
        }
        $options['use_pragma'] = false;
        $options['keepalive'] = true;
        parent::__construct($backend, $options, $this->session_id);
        if (!$this->backend->can('keepalive')) {
            throw new \Exception('The currently selected cache backend, '.get_class($this->backend).', does not support the keepalive feature which is required by the '.__CLASS__.' class.  Please choose a caching backend that supports the keepalive feature.');
        }
    }

    public function set(mixed $key, mixed $value, int $timeout = 0): bool
    {
        if (true !== $this->session_init && false === strpos(php_sapi_name(), 'cli')) {
            setcookie($this->session_name, $this->session_id, 0, Application::getPath());
            $this->session_init = true;
        }

        return parent::set($key, $value, $timeout);
    }

    public function clear(): void
    {
        parent::clear();
        if (ake($_COOKIE, $this->session_name) === $this->session_id) {
            setcookie($this->session_name, '', time() - 3600, Application::getPath());
        }
    }
}
