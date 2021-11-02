<?php

namespace Hazaar\Cache;

class Output extends \Hazaar\Cache {

    private $key;

    public function start($key) {

        if(($buffer = $this->get($key)) === FALSE) {

            $this->key = $key;

            ob_start();

            return FALSE;

        }

        return $buffer;

    }

    public function stop() {

        $buffer = ob_get_contents();

        ob_end_clean();

        $this->set($this->key, $buffer);

        return $buffer;

    }

}
