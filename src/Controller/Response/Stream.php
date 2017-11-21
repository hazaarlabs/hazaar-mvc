<?php

namespace Hazaar\Controller\Response;

class Stream extends \Hazaar\Controller\Response\HTTP\OK {

    private $final;

    private $type = 's';

    function __construct($final_packet) {

        parent::__construct("text/plain");

        $this->final = $final_packet;

        if(is_array($this->final)){

            $this->final = json_encode($this->final);

            $this->type = 'a';

        }

    }

    public function __writeoutput() {

        echo "\0" . $this->type . $this->final;

        flush();

    }

}
