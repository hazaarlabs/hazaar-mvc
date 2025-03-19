<?php

declare(strict_types=1);

namespace Hazaar\File\Exception;

use Hazaar\Exception;

class MoveFailed extends \Exception
{
    public function __construct(string $destFile)
    {
        parent::__construct("Unable to move uploaded file.  Destination file already exists at '{$destFile}'.  Use the \$overwrite parameter if your want to overwrite the file.");
    }
}
