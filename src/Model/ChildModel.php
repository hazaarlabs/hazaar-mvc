<?php

namespace Hazaar\Model;

class ChildModel extends Strict {

    function __construct($field_definition, $values = array()) {

        parent::loadDefinition($field_definition);

        if ($values)
            $this->populate($values);

    }

}

