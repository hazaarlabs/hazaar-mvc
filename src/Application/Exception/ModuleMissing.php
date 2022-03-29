<?php

namespace Hazaar\Application\Exception;

class ModuleMissing extends \Hazaar\Exception {

    protected $name = 'Module Missing';

    function __construct($modules) {

        if(! is_array($modules)) {

            $modules = [$modules];

        }

        parent::__construct('Required modules are missing. (' . implode(', ', $modules) . ')', 3);

    }

}
