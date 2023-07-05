<?php

namespace Hazaar\Auth;

class Helper extends Adapter\Session {

    function __construct($cache_config = [], $cache_backend = 'session'){

        parent::__construct($cache_config, $cache_backend);

        $this->identity = $this->session->hazaar_auth_identity;

    }

    public function queryAuth($identity, $credential = null, $extra = []) {

        /*
         * Helper does not support queryAuth as it doesn't know how to look up credentials
         */

        return false;

    }

}
