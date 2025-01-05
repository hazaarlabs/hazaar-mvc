<?php

namespace Hazaar\Console\Tool;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\File;

class DecryptCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('decrypt')
            ->setDescription('Decrypt a file using the Hazaar encryption system')
            ->addArgument('file', 'The file to encrypt')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $targetFile = $input->getArgument('file');
        $file = new File($targetFile);
        if ($file->exists()) {
            if (!$file->isEncrypted()) {
                throw new \Exception('File is not encrypted', 1);
            }
            $file->decrypt();
            $output->write('Decrypted '.$targetFile.PHP_EOL);
        }

        return 0;
    }
}
