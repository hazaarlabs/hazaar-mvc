<?php

namespace Hazaar\Model;

class Email extends Strict {

    function init() {

        return array(
            'address' => array(
                'type' => 'string',
                'validate' => array('with' => '/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i')
            )
        );

    }

    public function __construct($data){

        parent::__construct(array('address' => $data));

    }

    public function __toString(){

        return $this->address;

    }

}