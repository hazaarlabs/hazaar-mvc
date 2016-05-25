<?php

namespace Hazaar;

class Exception extends \Exception {

    protected $name;

    protected $short_message;

    function __construct($message, $code = 500, $short_message = null) {

        parent::__construct($message, $code);
        
        $this->short_message = $short_message;
    
    }

    public function getName() {

        if (! $this->name) {
            
            return preg_replace('/Hazaar\\\\Exception\\\\/', '', get_class($this));
        }
        
        return $this->name;
    
    }

    public function getShortMessage() {

        if ($this->short_message)
            return $this->short_message;
        
        $message = null;
        
        if ($this->code == 404)
            $message = "We can't seem to find the page you are looking for!";
        elseif ($this->code == 500)
            $message = "It would appear that we may have broken something!";
        
        return $message;
    
    }

}
