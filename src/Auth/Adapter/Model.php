<?php

namespace Hazaar\Auth\Adapter;

abstract class Model extends \Hazaar\Auth\Adapter implements _Interface {

    private $field_identity;

    private $field_credential;

    /*
     * Construct the new authentication object with the field names
     * in the model for id, user name, password and real name.
     */
    function __construct($args = null, $cache_config = [], $cache_backend = 'session') {

        parent::__construct($cache_config, $cache_backend);

        $this->init($args);

    }

    public function init(){

        return false;

    }

    public function insert(){

        return false;
        
    }

    public function delete(){

        return false;
        
    }

    public function setIdentityField($identity) {

        $this->field_identity = $identity;

    }

    public function setCredentialField($credential) {

        $this->field_credential = $credential;

    }

    public function addUser($identity, $credential) {

        $this->insert([
            $this->field_identity => $identity,
            $this->field_credential => $credential
        ]);

    }

    public function delUser($identity) {

        $this->delete([$this->field_identity => $identity]);

    }

}
