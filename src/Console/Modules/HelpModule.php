<?php

declare(strict_types=1);

namespace Hazaar\Console\Modules;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;

class HelpModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('help')
            ->setDescription('Display help information for a command.')
            ->addArgument('command', 'The command to display help for.')
            ->addGlobalOption('env', 'e', 'The environment to use.  Overrides the APPLICATION_ENV environment variable.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $command = $input->getArgument('command');
        if (null === $command) {
            return -1;
        }
        $module = $this->application->getCommandModule($command);
        if (null === $module) {
            return -1;
        }
        $command = $module->getCommand($command);
        if (null === $command) {
            return -1;
        }
        $output->write(PHP_EOL.'<fg=yellow>Command:</> '.$command->getName().PHP_EOL);
        $output->write(PHP_EOL.'<fg=yellow>Description:</> '.$command->getDescription().PHP_EOL);
        $output->write(PHP_EOL.'<fg=yellow>Usage:</> '.$command->getName());
        foreach ($command->getArguments() as $argument) {
            $output->write(' <fg=green>['.$argument['name'].']</>');
        }
        $output->write(PHP_EOL.PHP_EOL.'<fg=yellow>Arguments:</>'.PHP_EOL);
        foreach ($command->getArguments() as $argument) {
            $output->write('  <fg=green>'.$argument['name'].'</> - '.$argument['description'].PHP_EOL);
        }
        $output->write(PHP_EOL.'<fg=yellow>Options:</>'.PHP_EOL);
        foreach ($command->getOptions() as $option) {
            $output->write('  <fg=green>--'.$option['long'].'</>');
            if (null !== $option['short']) {
                $output->write(', <fg=green>-'.$option['short'].'</>');
            }
            $output->write(' - '.$option['description'].PHP_EOL);
        }

        return 0;
    }
}
