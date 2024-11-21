<?php

declare(strict_types=1);

namespace Hazaar\Parser;

use Hazaar\Parser\PHP\ParserFile;

class PHP
{
    public function __construct() {}

    public function parse(string $filename): ?ParserFile
    {
        if (!file_exists($filename)) {
            return null;
        }

        return new ParserFile($filename);
    }
}
