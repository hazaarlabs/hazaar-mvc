<?php

namespace Hazaar\Model;

/**
 * Email Address Strict Model
 * 
 * This is a simple model that enforces the format of an email address.
 * 
 * It currently has a single field called 'address' that is used to validate the email address format.
 * 
 * @author Jamie Carl <jamie@hazaarlabs.com>
 * 
 * @since 2.2
 */
class Email extends Strict {

    /**
     * Initialise the model.
     * 
     * @return array[]
     */
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