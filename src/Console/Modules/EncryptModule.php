<?php

namespace Hazaar\Console\Modules;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\File;

class EncryptModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('encrypt')
            ->setDescription('Encrypt a file using the Hazaar encryption system')
            ->addArgument('file', 'The file to encrypt')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $targetFile = $input->getArgument('file');
        $file = new File($targetFile);
        if ($file->exists()) {
            if ($file->isEncrypted()) {
                throw new \Exception('File is already encrypted', 1);
            }
            $file->encrypt();
            $output->write('Encrypted '.$targetFile.PHP_EOL);
        }

        return 0;
    }
}
