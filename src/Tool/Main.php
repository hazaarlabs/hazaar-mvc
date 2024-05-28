<?php

declare(strict_types=1);

namespace Hazaar\Tool;

use Hazaar\Application;
use Hazaar\Application\Config;
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
            'env' => ['e', 'env', 'string', 'Set the application environment.', 'config'],
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
                    $configCommand = ake($commandArgs, 0, 'list');
                    $env = ake($options, 'env', APPLICATION_ENV);
                    $config = new Config('application', $env);
                    $config->addOutputFilter(function ($value, $key) {
                        if (is_bool($value)) {
                            return strbool($value);
                        }

                        return $value;
                    }, true);

                    switch ($configCommand) {
                        case 'get':
                            if (!($configArg = ake($commandArgs, 1))) {
                                throw new \Exception('No configuration argument specified', 1);
                            }
                            $value = $config->get($configArg);
                            echo $configArg.'='.$value."\n";

                            break;

                        case 'set':
                            if (!($configArg = ake($commandArgs, 1))) {
                                throw new \Exception('No configuration argument specified', 1);
                            }
                            $configUpdates = array_unflatten($configArg);
                            if (0 === count($configUpdates)) {
                                throw new \Exception('No configuration value specified', 1);
                            }
                            $config->set($configArg, ake($commandArgs, 2));
                            if (false === $config->save()) {
                                throw new \Exception('Failed to save configuration', 1);
                            }

                            break;

                        case 'list':
                            echo 'app.env = '.APPLICATION_ENV."\n";
                            $list = $config->toDotNotation();
                            foreach ($list as $key => $value) {
                                echo $key.' = '.$value."\n";
                            }

                            break;
                    }

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
