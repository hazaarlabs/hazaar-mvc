<?php

namespace Hazaar\Console\Tool;

use Hazaar\Application\Config;
use Hazaar\Auth\Adapter\DBITable;
use Hazaar\Auth\Adapter\HTPasswd;
use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class ToolCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('user')
            ->setDescription('Run a tool command')
            ->addArgument('command', 'The tool command to run')
            ->addArgument('user', 'The user to operate on')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $command = $input->getArgument('command');
        if (!$command) {
            $output->write('No command specified!'.PHP_EOL);

            return -1;
        }
        $user = $input->getArgument('user');
        if (!$user) {
            $output->write('No user specified!'.PHP_EOL);

            return -1;
        }
        $env = $input->getOption('env') ?? defined('APPLICATION_ENV') ? APPLICATION_ENV : 'development';
        $appConfig = Config::getInstance($env);

        switch ($command) {
            case 'add':
                $auth = $appConfig['auth']->has('table') ?
                    new DBITable(Adapter::getInstance(), $appConfig['auth']) :
                    new HTPasswd($appConfig['auth']);
                $credential = self::readCredential();
                if ($auth->create($user, $credential)) {
                    $output->write('User added: '.$user.PHP_EOL);
                } else {
                    throw new \Exception('Failed to add user', 1);
                }

                break;

            case 'del':
                $auth = $appConfig['auth']->has('table') ?
                    new DBITable(Adapter::getInstance(), $appConfig['auth']) :
                    new HTPasswd($appConfig['auth']);
                if ($auth->delete($user)) {
                    $output->write('User deleted: '.$user.PHP_EOL);
                } else {
                    throw new \Exception('Failed to delete user', 1);
                }

                break;

            case 'passwd':
                $auth = $appConfig['auth']->has('table') ?
                    new DBITable(Adapter::getInstance(), $appConfig['auth']) :
                    new HTPasswd($appConfig['auth']);
                $credential = self::readCredential();
                if ($auth->update($user, $credential)) {
                    $output->write('Password updated for user: '.$user.PHP_EOL);
                } else {
                    throw new \Exception('Failed to update password', 1);
                }
        }

        return 0;
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
