<?php

namespace Hazaar\Controller\Response;

class Javascript extends File {
    
    private $compress = false;

    public function setCompression($toggle) {

        $this->compress = $toggle;

    }

    public function getContent() {

        if($output = parent::getContent()) {

            if($this->compress) {

                $packer = new \Hazaar\Packer\JavaScriptPacker($output);

                $output = $packer->pack();

            }

        }

        return $output;

    }

}