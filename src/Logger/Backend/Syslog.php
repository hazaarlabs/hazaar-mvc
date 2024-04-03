<?php

namespace Hazaar\Logger\Backend;

class Syslog extends \Hazaar\Logger\Backend {

    public function write($tag, $msg, $level = LOG_NOTICE, $request = null) {

        $remote = $request instanceof \Hazaar\Application\Request\Http ? $request->getRemoteAddr() : ake($_SERVER, 'REMOTE_ADDR', '--');

        syslog($level, $remote . ' : ' . $tag . ' : ' . $msg);

    }

    public function trace() {

    }

}
