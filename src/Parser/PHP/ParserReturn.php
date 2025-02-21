<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

class ParserReturn extends ParserParameter
{
    public function __toString()
    {
        return $this->type ?? 'void';
    }
}
