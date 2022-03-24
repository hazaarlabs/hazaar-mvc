<?php

namespace Hazaar\Mail\Transport;

interface _Interface {

    public function send($to, $subject = null, $message = null, $extra_headers = array(), $dsn_types = array());

}
