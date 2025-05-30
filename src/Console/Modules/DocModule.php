<?php

namespace Hazaar\Console\Modules;

use Hazaar\Console\API\Documentor;
use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;

class DocModule extends Module
{
    private Documentor $doc;

    protected function configure(): void
    {
        $this->setName('doc')->setDescription('Work with API documentation');
        $this->addCommand(name: 'compile', callback: [$this, 'generate'])
            ->setDescription(description: 'Generate API documentation')
            ->addOption(long: 'title', short: 't', description: 'The title of the documentation', takesValue: true, default: 'API Documentation', valueType: 'title')
            ->addOption(long: 'scan', description: 'The path to scan for classes', takesValue: true, valueType: 'dir')
            ->addOption('verbose', short: 'v', description: 'Enable verbose output', takesValue: false, default: false)
            ->addArgument(name: 'output', description: 'The output path for the documentation')
        ;
        $this->addCommand('index', [$this, 'index'])
            ->setDescription('Generate an API documentation index')
            ->addOption(long: 'title', description: 'The title of the documentation', takesValue: true, default: 'API Documentation')
            ->addOption(long: 'scan', description: 'The path to scan for classes', takesValue: true, default: '.')
            ->addOption('format', description: 'The index format to generate', takesValue: true, default: 'vuepress')
            ->addArgument('output', description: 'The output path for the documentation')
        ;
    }

    protected function prepare(Input $input, Output $output): int
    {
        $outputPath = $input->getArgument('output');
        if (!$outputPath) {
            throw new \InvalidArgumentException('No output path specified', 1);
        }
        $title = $input->getOption('title') ?? 'API Documentation';
        $this->doc = new Documentor(Documentor::DOC_OUTPUT_MARKDOWN, $title);
        $this->doc->setCallback(function (string $message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        $this->doc->setOutputPath($outputPath);
        $scanPath = $input->getOption('scan') ?? '.';
        if (!$this->doc->setScanPath($scanPath)) {
            throw new \InvalidArgumentException("Invalid scan path: {$scanPath}", 1);
        }

        return 0;
    }

    protected function generate(Input $input, Output $output): int
    {
        $result = $this->doc->generate();
        if ($result) {
            $output->write('Output: '.realpath($this->doc->getOutputPath()).PHP_EOL);
        } else {
            $output->write('<fg=red>Error generating documentation</>'.PHP_EOL);
        }

        return $result ? 0 : 1;
    }

    protected function index(Input $input, Output $output): int
    {
        $result = $this->doc->generateIndex($input->getOption('format', 'vuepress'));

        return $result ? 0 : 1;
    }
}
