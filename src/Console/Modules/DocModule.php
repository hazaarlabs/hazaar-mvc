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

    protected function prepare(Input $input, Output $output): void
    {
        $outputPath = $input->getArgument('output');
        if (!$outputPath) {
            throw new \Exception('No output path specified', 1);
        }
        $title = $input->getOption('title') ?? 'API Documentation';
        $this->doc = new Documentor(Documentor::DOC_OUTPUT_MARKDOWN, $title);
        $this->doc->setCallback(function (string $message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        $this->doc->setOutputPath($outputPath);
        $this->doc->setScanPath($input->getOption('scan') ?? '.');
    }

    protected function generate(Input $input, Output $output): int
    {
        $result = $this->doc->generate();

        return $result ? 0 : 1;
    }

    protected function index(Input $input, Output $output): int
    {
        $result = $this->doc->generateIndex($input->getOption('format', 'vuepress'));

        return $result ? 0 : 1;
    }
}
