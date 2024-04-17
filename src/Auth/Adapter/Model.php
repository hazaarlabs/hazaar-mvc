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
            $this->field_credential => $credential
        ]);
    }

    public function delUser($identity)
    {
        return $this->delete([$this->field_identity => $identity]);
    }

    public function authenticate($identity = null, $credential = null, $autologin = false){
        $result = parent::authenticate($identity, $credential, $autologin);
        if($result === true)
            $this->authenticationSuccess($identity, $this->extra);
        else
            $this->authenticationFailure($identity, $this->extra);
        return $result;
    }

}
