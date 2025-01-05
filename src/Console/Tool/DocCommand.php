<?php

namespace Hazaar\Console\Tool;

use Hazaar\Console\API\Documentor;
use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;

class DocCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('doc')
            ->setDescription('Generate API documentation')
            ->addOption('title', null, 'The title of the documentation')
            ->addOption('scan', null, 'The path to scan for classes')
            ->addArgument('output', 'The output path for the documentation')
        ;
    }

    protected function execute(Input $input, Output $output): int
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
        $doc->generate($scanPath, $outputPath);

        return 0;
    }
}
