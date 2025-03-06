<?php

namespace Hazaar\Console\Modules;

use Hazaar\Console\API\Documentor;
use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;

class DocModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand(name: 'doc', callback: [$this, 'generate'])
            ->setDescription(description: 'Generate API documentation')
            ->addOption(long: 'title', description: 'The title of the documentation')
            ->addOption(long: 'scan', description: 'The path to scan for classes')
            ->addOption(long: 'sidebar', description: 'The sidebar format to generate')
            ->addArgument(name: 'output', description: 'The output path for the documentation')
        ;

        $this->addCommand('index', [$this, 'index'])
            ->setDescription('Generate an API documentation index')
            ->addOption(long: 'title', description: 'The title of the documentation')
            ->addOption(long: 'scan', description: 'The path to scan for classes')
            ->addOption('format', description: 'The index format to generate')
            ->addArgument('output', description: 'The output path for the documentation')
        ;
    }

    protected function generate(Input $input, Output $output): int
    {
        $outputPath = $input->getArgument('output');
        if (!$outputPath) {
            throw new \Exception('No output path specified', 1);
        }
        if (!is_dir($outputPath)) {
            throw new \Exception('Output path does not exist', 1);
        }
        if (!is_writable($outputPath)) {
            throw new \Exception('Output path is not writable', 1);
        }
        $title = $input->getOption('title') ?? 'API Documentation';
        $scanPath = $input->getOption('scan') ?? '.';
        $doc = new Documentor(Documentor::DOC_OUTPUT_MARKDOWN, $title);
        $doc->setCallback(function (string $message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        $result = $doc->generate($scanPath, $outputPath);

        return $result ? 0 : 1;
    }

    protected function index(Input $input, Output $output): int
    {
        $outputFile = $input->getArgument('output');
        if (!$outputFile) {
            throw new \Exception('No output file specified', 1);
        }
        $outputPath = dirname($outputFile);
        if (!is_dir(dirname($outputPath))) {
            throw new \Exception('Output path does not exist', 1);
        }
        if (!is_writable($outputPath)) {
            throw new \Exception('Output path is not writable', 1);
        }
        $title = $input->getOption('title') ?? 'API Documentation';
        $scanPath = $input->getOption('scan') ?? '.';
        $doc = new Documentor(Documentor::DOC_OUTPUT_MARKDOWN, $title);
        $doc->setCallback(function (string $message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        $result = $doc->generateIndex($scanPath, $outputPath, $input->getOption('format', 'vuepress'));

        return $result ? 0 : 1;
    }
}
