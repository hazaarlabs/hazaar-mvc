<?php

namespace Hazaar\Http\Exception;

class CertificateNotFound extends \Hazaar\Exception {
    
    function __construct(){
        
        parent::__construct('File not found while trying to load local client certificate');
        
    }
    
}
