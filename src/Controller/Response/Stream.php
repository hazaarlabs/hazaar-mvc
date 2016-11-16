<?php

namespace Hazaar\Controller\Response;

class Stream extends \Hazaar\Controller\Response\HTTP\OK {

    private $final;

    function __construct($final_packet) {

        parent::__construct("text/plain");

        $this->final = $final_packet;

        if(is_array($this->final))
            $this->final = json_encode($this->final);

    }

    public function __writeoutput() {

        echo dechex(strlen($this->final)) . "\0" . $this->final;

        flush();

    }

}
