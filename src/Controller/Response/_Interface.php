<?php

namespace Hazaar\Controller\Response;

interface _Interface {

    public function getContent();

    public function setContent($content);

    public function addContent($content);

    public function __writeOutput();

}

