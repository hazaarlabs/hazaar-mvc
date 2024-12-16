<?php

namespace Hazaar\Model;

use Hazaar\Model;

abstract class Rule
{
    public function evaluate(mixed $value, Model $model, \ReflectionProperty &$property): void {}
}
