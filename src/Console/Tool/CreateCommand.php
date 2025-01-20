<?php

declare(strict_types=1);

namespace Hazaar\Console\Tool;

use Hazaar\Application\FilePath;
use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\Template\Smarty;

class CreateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('create')
            ->setDescription('Create a new application object (view, controller or model).')
            ->addArgument('type', 'The type of object to create (layout, view, controller, controller_basic, controller_action, model).')
            ->addArgument('name', 'The name of the object to create.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $type = $input->getArgument('type');
        $name = $input->getArgument('name');
        if (!$name) {
            $output->write('<fg=red>Missing object name!</>'.PHP_EOL);

            return 1;
        }

        try {
            if (self::create($type, $name)) {
                $output->write('<fg=green>Object created successfully!</>'.PHP_EOL);
            } else {
                $output->write('<fg=red>Failed to create object!</>'.PHP_EOL);

                return 1;
            }
        } catch (\Exception $e) {
            $output->write('<fg=red>'.$e->getMessage().'</>'.PHP_EOL);

            return 1;
        }

        return 0;
    }

    private static function create(string $type, string $name, string $targetDir = '.'): bool
    {
        $fileType = null;
        $params = [];
        $targetFilename = $name;
        $templateFile = strtolower($type).'.tpl';

        switch ($type) {
            case 'layout':
                $fileType = FilePath::VIEW;
                $targetFilename = strtolower($name).'.tpl';

                break;

            case 'view':
                $fileType = FilePath::VIEW;
                $targetFilename = strtolower($name).'.tpl';

                break;

            case 'controller':
            case 'controller_basic':
                $fileType = FilePath::CONTROLLER;
                $templateFile = 'controller_basic.tpl';
                $targetFilename = ucfirst($name).'.php';
                $params = [
                    'controllerName' => ucfirst($name),
                    'viewName' => strtolower($name),
                ];

                break;

            case 'controller_action':
                $fileType = FilePath::CONTROLLER;
                $targetFilename = ucfirst($name).'.php';
                $params = [
                    'controllerName' => ucfirst($name),
                    'viewName' => strtolower($name),
                ];

                break;

            case 'model':
                $fileType = FilePath::MODEL;
                $targetFilename = $name.'.tpl';
                $params['modelName'] = ucfirst($name);

                break;
        }
        if (!$fileType) {
            throw new \Exception('Invalid object type: '.$type, 1);
        }
        $targetFile = $targetDir.DIRECTORY_SEPARATOR.$targetFilename;
        if (!($sourceFile = realpath(__DIR__.'/../../../libs/templates/'.$templateFile))) {
            throw new \Exception('Template file not found: '.$templateFile, 1);
        }
        if (file_exists($targetFile)) {
            throw new \Exception('File already exists: '.$targetFile, 1);
        }
        if ('.tpl' === substr($targetFilename, -4)) {
            $result = file_put_contents($targetFile, file_get_contents($sourceFile));
        } else {
            $sourceTemplate = new Smarty();
            $sourceTemplate->loadFromFile($sourceFile);
            $result = file_put_contents($targetFile, "<?php\n\n".$sourceTemplate->render($params));
        }

        return $result > 0;
    }
}
