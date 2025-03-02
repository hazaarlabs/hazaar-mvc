<?php

declare(strict_types=1);

namespace Hazaar\File\Template\Exception;

use Hazaar\File;

class RenderFailed extends \Exception
{
    public function __construct(string $message, File $sourceFile, int $line = 0, ?\Throwable $previous = null)
    {
        parent::__construct('An error occurred parsing the Smarty template: '.$message, 500, $previous);
        $this->file = $sourceFile->fullpath();
        $this->line = $line;
    }
}
