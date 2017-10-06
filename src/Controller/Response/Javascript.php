<?php

namespace Hazaar\Controller\Response;

class Javascript extends File {

    function __construct($source = NULL) {

        parent::__construct($source);

        $this->setContentType('application/javascript');

    }

    public function getContent() {

        if($output = parent::getContent()) {

            if($this->compress)
                $output = Packer\JavaScript::minify($output);

        }

        return $output;

    }

}