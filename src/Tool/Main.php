<?php

declare(strict_types=1);

namespace Hazaar\Tool;

use Hazaar\Application;
use Hazaar\Application\Config;
use Hazaar\Application\Request\CLI;
use Hazaar\Auth\Adapter\DBITable;
use Hazaar\Auth\Adapter\HTPasswd;
use Hazaar\DBI\Adapter;
use Hazaar\File;
use Hazaar\File\Template\Smarty;
use Hazaar\Loader;

class Main
{
    public static function run(Application $application, CLI $request): int
    {
        $request->setOptions([
            'help' => ['h', 'help', null, 'Display this help message.'],
            'env' => ['e', 'env', 'string', 'Set the application environment.', 'config'],
            'scan' => ['s', 'scan', 'path', 'Scan the application for new classes.', 'doc'],
            'title' => ['t', 'title', 'string', 'Set the title of the documentation.', 'doc'],
        ]);
        $request->setCommands([
            'create' => ['Create a new application object (view, controller or model).'],
            'config' => ['Manage application configuration.'],
            'show' => ['Show the contents of a configuration file, decrypting if neccessary.'],
            'encrypt' => ['Encrypt a configuration file using the application secret key.'],
            'decrypt' => ['Decrypt a configuration file using the application secret key.'],
            'adduser' => ['Add a new user to the application.'],
            'deluser' => ['Delete a user from the application.'],
            'passwd' => ['Change a user password.'],
            'doc' => ['Generate documentation for the application.'],
        ]);
        if (!($command = $request->getCommand($commandArgs))) {
            $request->showHelp();

            return 1;
        }
        $options = $request->getOptions();
        $appConfig = Config::getInstance('application', APPLICATION_ENV);
        if (!isset($appConfig['auth'])) {
            $appConfig['auth'] = [];
        }
        $appConfig['auth']['storage'] = 'session';
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
                    $config = Config::getInstance('application', $env);

                    switch ($configCommand) {
                        case 'get':
                            if (!($configArg = ake($commandArgs, 1))) {
                                throw new \Exception('No configuration argument specified', 1);
                            }
                            $value = $config[$configArg];
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
                                $config[$key] = $value;
                            }
                            if (false === $config->save()) {
                                throw new \Exception('Failed to save configuration', 1);
                            }

                            break;

                        case 'list':
                            echo 'app.env = '.APPLICATION_ENV."\n";
                            $list = array_to_dot_notation($config->toArray());
                            foreach ($list as $key => $value) {
                                echo $key.' = '.$value."\n";
                            }

                            break;
                    }

                    break;

                case 'show':
                    $file = new File($application->loader->getFilePath(FILE_PATH_CONFIG, $commandArgs[0]));
                    if ($file->exists()) {
                        echo json_encode($file->parseJSON(), JSON_PRETTY_PRINT)."\n"; // Output pretty JSON
                    } else {
                        throw new \Exception('File not found', 1);
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

                case 'adduser':
                    $auth = $appConfig['auth']->has('table') ?
                        new DBITable(Adapter::getInstance(), $appConfig['auth']) :
                        new HTPasswd($appConfig['auth']);
                    $credential = self::readCredential();
                    if ($auth->create($commandArgs[0], $credential)) {
                        echo 'User added: '.$commandArgs[0]."\n";
                    } else {
                        throw new \Exception('Failed to add user', 1);
                    }

                    break;

                case 'deluser':
                    $auth = $appConfig['auth']->has('table') ?
                        new DBITable(Adapter::getInstance(), $appConfig['auth']) :
                        new HTPasswd($appConfig['auth']);
                    if ($auth->delete($commandArgs[0])) {
                        echo 'User deleted: '.$commandArgs[0]."\n";
                    } else {
                        throw new \Exception('Failed to delete user', 1);
                    }

                    break;

                case 'passwd':
                    $auth = $appConfig['auth']->has('table') ?
                        new DBITable(Adapter::getInstance(), $appConfig['auth']) :
                        new HTPasswd($appConfig['auth']);
                    $credential = self::readCredential();
                    if ($auth->update($commandArgs[0], $credential)) {
                        echo 'Password updated for user: '.$commandArgs[0]."\n";
                    } else {
                        throw new \Exception('Failed to update password', 1);
                    }

                    // no break
                case 'doc':
                    if (!isset($commandArgs[0])) {
                        throw new \Exception('No output path specified', 1);
                    }
                    $scanPath = ake($options, 'scan', '.');
                    $doc = new APIDoc(APIDoc::DOC_OUTPUT_MARKDOWN, ake($options, 'title', 'API Documentation'));
                    $doc->generate($scanPath, $commandArgs[0]);
            }
        } catch (\Throwable $e) {
            echo $e->getMessage()."\n";
            $code = intval($e->getCode());
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

    private static function readCredential(): string
    {
        system('stty -echo');
        $credential = '';
        while (strlen($credential) < 6) {
            $credential = readline('Enter password: ');
            if (strlen($credential) < 6) {
                echo "Password must be at least 6 characters long\n";
            }
        }
        $credential_confirm = readline('Confirm password: ');
        system('stty echo');
        if ($credential !== $credential_confirm) {
            throw new \Exception('Passwords do not match', 1);
        }

        return $credential;
    }
}
