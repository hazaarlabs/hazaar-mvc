<?php

declare(strict_types=1);

namespace Hazaar\Tool;

use Hazaar\Application;
use Hazaar\Application\Request\CLI;
use Hazaar\File;

class Main
{
    public static function run(Application $application): int
    {
        if (!$application->request instanceof CLI) {
            return 255;
        }
        $application->request->setOptions([
            'help' => ['h', 'help', null, 'Display this help message.'],
        ]);
        $application->request->setCommands([
            'create' => ['Create a new application object (view, controller or model).'],
            'config' => ['Manage application configuration.'],
            'encrypt' => ['Encrypt a configuration file using the application secret key.'],
            'decrypt' => ['Decrypt a configuration file using the application secret key.'],
        ]);
        if (!($command = $application->request->getCommand($commandArgs))) {
            $application->request->showHelp();

            return 1;
        }
        $options = $application->request->getOptions();
        $code = 1;

        try {
            switch ($command) {
                case 'create':
                    echo 'Creating new '.$commandArgs[0].' object: '.$commandArgs[1]."\n";

                    break;

                case 'config':
                    echo 'Configuring application: '.$commandArgs[0]."\n";

                    break;

                case 'encrypt':
                    $file = new File($application->loader->getFilePath(FILE_PATH_CONFIG, $commandArgs[0]));
                    if ($file->exists()) {
                        if ($file->isEncrypted()) {
                            throw new \Exception('File is already encrypted', 1);
                        }
                        $file->encrypt();
                        echo 'Encrypted '.$commandArgs[0]."\n";
                    }

                    break;

                case 'decrypt':
                    $file = new File($application->loader->getFilePath(FILE_PATH_CONFIG, $commandArgs[0]));
                    if ($file->exists()) {
                        if (!$file->isEncrypted()) {
                            throw new \Exception('File is not encrypted', 1);
                        }
                        $file->decrypt();
                        echo 'Decrypted '.$commandArgs[0]."\n";
                    }

                    break;
            }
        } catch (\Throwable $e) {
            echo $e->getMessage()."\n";
            $code = $e->getCode();
        }

        return $code;
    }
}
