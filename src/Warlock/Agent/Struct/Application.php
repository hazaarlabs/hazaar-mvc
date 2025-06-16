<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent\Struct;

class Application
{
    public string $autoload;

    public function __construct(
        public string $path = '',
        public string $env = ''
    ) {
        $includedFiles = get_included_files();
        $autoloadFile = '';
        foreach ($includedFiles as $file) {
            if (str_ends_with($file, 'vendor/autoload.php')) {
                $autoloadFile = $file;

                break;
            }
        }
        $this->autoload = $autoloadFile;
    }
}
