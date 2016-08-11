<?php

namespace Hazaar\Auth;

class Helper extends Adapter {

    public function queryAuth($identity, $credential = null, $extra = array()) {

        /*
         * Helper does not support queryAuth as it doesn't know how to look up credentials
         */

        return false;

    }

}
