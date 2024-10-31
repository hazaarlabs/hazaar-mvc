<?php

namespace Hazaar\Model\Interfaces;

use Hazaar\Model;

interface Attribute
{
    public function check(Model $model, \ReflectionProperty &$property): void;
}
