<?php

namespace Hazaar\Console\Modules;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\File;

class FileModule extends Module
{
    protected function configure(): void
    {
        $this->setName('file')->setDescription('Work with files and encryption');
        $this->addCommand('encrypt', [$this, 'encryptFile'])
            ->setDescription('Encrypt a file using the Hazaar encryption system')
            ->addArgument('file', 'The file to encrypt')
        ;
        $this->addCommand('decrypt', [$this, 'decryptFile'])
            ->setDescription('Decrypt a file using the Hazaar encryption system')
            ->addArgument('file', 'The file to decrypt')
        ;
        $this->addCommand('check', [$this, 'checkFileEncrypted'])
            ->setDescription('Check if a file is encrypted')
            ->addArgument('file', 'The file to check')
        ;
        $this->addCommand('view', [$this, 'viewEncryptedFile'])
            ->setDescription('View the contents of an encrypted file')
            ->addArgument('file', 'The file to view')
        ;
    }

    protected function encryptFile(Input $input, Output $output): int
    {
        $targetFile = $input->getArgument('file');
        $file = new File($targetFile);
        if (!$file->exists()) {
            throw new \Exception('File does not exist', 255);
        }
        if ($file->isEncrypted()) {
            throw new \Exception('File is already encrypted', 1);
        }
        $file->encrypt();
        $output->write('Encrypted '.$targetFile.PHP_EOL);
        $output->write('Encrypted file size: '.$file->size().' bytes'.PHP_EOL);

        return 0;
    }

    protected function decryptFile(Input $input, Output $output): int
    {
        $targetFile = $input->getArgument('file');
        $file = new File($targetFile);
        if (!$file->exists()) {
            throw new \Exception('File does not exist', 255);
        }
        if (!$file->isEncrypted()) {
            throw new \Exception('File is not encrypted', 1);
        }
        $file->decrypt();
        $output->write('Decrypted '.$targetFile.PHP_EOL);

        return 0;
    }

    protected function checkFileEncrypted(Input $input, Output $output): int
    {
        $targetFile = $input->getArgument('file');
        $file = new File($targetFile);
        if (!$file->exists()) {
            throw new \Exception('File does not exist', 255);
        }
        if ($file->isEncrypted()) {
            $output->write('File is encrypted'.PHP_EOL);

            return 1;
        }
        $output->write('File is not encrypted'.PHP_EOL);

        return 0;
    }

    protected function viewEncryptedFile(Input $input, Output $output): int
    {
        $targetFile = $input->getArgument('file');
        $file = new File($targetFile);
        if (!$file->exists()) {
            throw new \Exception('File does not exist', 255);
        }
        if (!$file->isEncrypted()) {
            throw new \Exception('File is not encrypted', 1);
        }
        $output->write('Encrypted '.$targetFile.PHP_EOL);
        $output->write('Encrypted file size: '.$file->size().' bytes'.PHP_EOL.PHP_EOL);
        $output->write($file->getContents().PHP_EOL);

        return 0;
    }
}
