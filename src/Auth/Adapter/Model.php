<?php

namespace Hazaar\Auth\Adapter;

abstract class Model extends Session
{
    private $field_identity;
    private $field_credential;

    /*
     * Construct the new authentication object with the field names
     * in the model for id, user name, password and real name.
     */
    public function __construct($args = null, $cache_config = [], $cache_backend = 'session')
    {
        parent::__construct($cache_config, $cache_backend);
        $this->init($args);
    }

    public function init()
    {
        return false;
    }

    public function insert($data)
    {
        return false;
    }

    public function delete($criteria)
    {
        return false;
    }

    public function setIdentityField($identity)
    {
        $this->field_identity = $identity;
    }

    public function setCredentialField($credential)
    {
        $this->field_credential = $credential;
    }

    public function addUser($identity, $credential)
    {
        return $this->insert([
            $this->field_identity => $identity,
            $this->field_credential => $credential,
        ]);
    }

    public function delUser($identity)
    {
        return $this->delete([$this->field_identity => $identity]);
    }

    public function authenticate($identity = null, $credential = null, $autologin = false, &$data = null)
    {
        $result = parent::authenticate($identity, $credential, $autologin, $data);
        if (true === $result) {
            $this->authenticationSuccess($identity, $data);
        } else {
            $this->authenticationFailure($identity, $data);
        }

        return $result;
    }

    public function deauth()
    {
        $identity = $this->identity;
        $this->authenticationTerminated($identity);

        return parent::deauth();
    }

    /**
     * Overload function called when a user is successfully authenticated.
     *
     * This can occur when calling authenticate() or authenticated() where a session has been saved.  This default method does nothing but can
     * be overridden.
     *
     * @param string $identity
     * @param mixed  $data
     */
    protected function authenticationSuccess($identity, $data) {}

    /**
     * Overload function called when a user fails to authenticate.
     *
     * This can occur when calling authenticate() or authenticated() where a session has been saved.  This default method does nothing but can
     * be overridden.
     *
     * @param string $identity
     * @param mixed  $data
     */
    protected function authenticationFailure($identity, $data) {}

    /**
     * Overload function called when a user is deauthenticated.
     *
     * This can occur when calling deauth().  This default method does nothing but can be overridden.
     *
     * @param string $identity
     */
    protected function authenticationTerminated($identity) {}
}
