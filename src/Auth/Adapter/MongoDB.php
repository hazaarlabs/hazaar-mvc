<?php

namespace Hazaar\Auth\Adapter;

class MongoDB extends \Hazaar\Auth\Adapter implements _Interface {

    private $mongoDB;

    private $collection;

    private $field_identity;

    private $field_credential;

    /*
     * Construct the new authentication object with the field names
     * in the model for id, user name, password and real name.
     */
    function __construct(\Hazaar\MongoDB $mongoDB, $collection = 'users', $identity = '_id', $credential = 'password', $namespace = null, $cache_config = array(), $cache_backend = 'session') {

        $this->collection = $collection;

        $this->field_identity = $identity;

        $this->field_credential = $credential;

        $this->mongoDB = $mongoDB;

        parent::__construct($namespace, $cache_config, $cache_backend);

    }

    public function setCollection($collection) {

        $this->collection = $collection;

    }

    public function setIdentityField($identity) {

        $this->field_identity = $identity;

    }

    public function setCredentialField($credential) {

        $this->field_credential = $credential;

    }

    public function addUser($identity, $credential) {

        $collection = $this->mongoDB->selectCollection($this->collection);

        $doc = array(
            $this->field_identity   => $identity,
            $this->field_credential => $this->getCredential($credential)
        );

        $collection->insert($doc);

        return (string)$doc['_id'];

    }

    public function delUser($identity) {

        $collection = $this->mongoDB->selectCollection($this->collection);

        return $collection->remove(array($this->field_identity => $identity));

    }

    /*
     * We must provide a queryAuth method for the auth base class to use to look up details
     */
    public function queryAuth($identity, $extras = array()) {

        if(! $identity)
            return false;

        if(! $this->field_identity)
            throw new \Exception('Identity field not set in ' . get_class($this) . '. This is required.');

        if($this->field_identity == '_id' && ! $identity instanceof \MongoId) {

            $identity = new \MongoId($identity);

        }

        $criteria = array($this->field_identity => $identity);

        $extras['id'] = '_id';

        $collection = $this->mongoDB->selectCollection($this->collection);

        if(! ($user = $collection->findOne($criteria))) {

            return false;

        }

        $details = array('identity' => (string)$user[$this->field_identity]);

        if($this->field_credential)
            $details['credential'] = $user[$this->field_credential];

        if(is_array($extras)) {

            $details['data'] = array();

            foreach($extras as $map => $key) {

                if(! is_string($map))
                    $map = $key;

                if(array_key_exists($key, $user))
                    $details['data'][$map] = $user[$key];

            }

        }

        unset($user[$this->field_identity]);

        unset($user[$this->field_credential]);

        return $details;

    }

}

