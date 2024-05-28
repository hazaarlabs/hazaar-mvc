<?php

declare(strict_types=1);

namespace Hazaar\Tool;

use Hazaar\Application;
use Hazaar\Application\Config;
use Hazaar\Application\Request\CLI;
use Hazaar\File;
use Hazaar\File\Template\Smarty;
use Hazaar\Loader;

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
        $code = 0;

        try {
            switch ($command) {
                case 'create':
                    echo 'Creating new '.$commandArgs[0].' object: '.$commandArgs[1]."\n";
                    if (!self::create($commandArgs[0], $commandArgs[1], $application->loader)) {
                        throw new \Exception('Failed to create object', 1);
                    }

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
                            $configUpdates = array_unflatten($configArg, '=', ';');
                            if (0 === count($configUpdates)) {
                                throw new \Exception('No configuration value specified', 1);
                            }
                            foreach ($configUpdates as $key => $value) {
                                $config->set($key, $value);
                            }
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

    private static function create(string $type, string $name, Loader $loader): bool
    {
        $fileType = null;
        $params = [];
        $targetFilename = $name;
        $templateFile = strtolower($type).'.tpl';

        switch ($type) {
            case 'layout':
                $fileType = FILE_PATH_VIEW;
                $targetFilename = strtolower($name).'.tpl';

                break;

            case 'view':
                $fileType = FILE_PATH_VIEW;
                $targetFilename = strtolower($name).'.tpl';

                break;

            case 'controller':
            case 'controller_basic':
                $fileType = FILE_PATH_CONTROLLER;
                $templateFile = 'controller_basic.tpl';
                $targetFilename = ucfirst($name).'.php';
                $params = [
                    'controllerName' => ucfirst($name),
                    'viewName' => strtolower($name),
                ];

                break;

            case 'controller_action':
                $fileType = FILE_PATH_CONTROLLER;
                $targetFilename = ucfirst($name).'.php';
                $params = [
                    'controllerName' => ucfirst($name),
                    'viewName' => strtolower($name),
                ];

                break;

            case 'model':
                $fileType = FILE_PATH_MODEL;
                $targetFilename = $name.'.tpl';
                $params['modelName'] = ucfirst($name);

                break;
        }
        if (!$fileType) {
            throw new \Exception('Invalid object type: '.$type, 1);
        }
        $targetDir = $loader->getFilePath($fileType);
        $targetFile = $targetDir.DIRECTORY_SEPARATOR.$targetFilename;
        if (!($sourceFile = $loader->getFilePath(FILE_PATH_SUPPORT, 'templates/'.$templateFile))) {
            throw new \Exception('Template file not found: '.$templateFile, 1);
        }
        if (file_exists($targetFile)) {
            throw new \Exception('File already exists: '.$targetFile, 1);
        }
        if ('.tpl' === substr($targetFilename, -4)) {
            $result = file_put_contents($targetFile, file_get_contents($sourceFile));
        } else {
            $sourceTemplate = new Smarty($sourceFile);
            $result = file_put_contents($targetFile, "<?php\n\n".$sourceTemplate->render($params));
        }

        return $result > 0;
    }
}
