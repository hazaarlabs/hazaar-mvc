<?php

namespace Hazaar\Controller\Response;

class Stream extends \Hazaar\Controller\Response\HTTP\OK {

    private $final;

    private $type = 's';

    function __construct($final_packet) {

        parent::__construct("text/plain");

        $this->final = $final_packet;

        if($final_packet instanceof \Exception){

            $error = array(
                'ok' => false,
                'error' => array(
                    'type' => $final_packet->getCode(),
                    'status' => 'Stream Error',
                    'str' => $final_packet->getMessage()
                )
             );

            if(ini_get('display_errors')){

                $error['error']['line'] = $final_packet->getLine();

                $error['error']['file'] = $final_packet->getFile();

                $error['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            }

            $this->type = 'e';

            $this->final = json_encode($error);

        }elseif(is_array($final_packet)){

            $this->final = json_encode($final_packet);

            $this->type = 'a';

        }

    }

    public function __writeoutput() {

        echo "\0" . $this->type . $this->final;

        flush();

    }

}
