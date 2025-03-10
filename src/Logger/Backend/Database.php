<?php

declare(strict_types=1);

namespace Hazaar\Logger\Backend;

use Hazaar\DBI\Adapter;
use Hazaar\Logger\Backend;
use Hazaar\Util\DateTime;

class Database extends Backend
{
    private bool $failed = false;
    private Adapter $db;

    public function init(): void
    {
        $this->setDefaultOption('host', 'localhost');
        $this->setDefaultOption('database', 'hazaar_default');
        $this->setDefaultOption('table', 'log');
        $this->setDefaultOption('write_ip', true);
        $this->setDefaultOption('write_timestamp', true);
        $this->setDefaultOption('write_uri', true);
        $config = [
            'type' => $this->getOption('type'),
            'host' => $this->getOption('host'),
            'dbname' => $this->getOption('database'),
        ];
        if ($this->hasOption('username')) {
            $config['user'] = $this->getOption('username');
        }
        if ($this->hasOption('password')) {
            $config['password'] = $this->getOption('password');
        }
        $this->db = new Adapter($config);
    }

    public function write(string $message, int $level = LOG_INFO, ?string $tag = null): void
    {
        if (true === $this->failed) {
            return;
        }

        try {
            $row = [
                'message' => $message,
                'level' => $level,
                'tag' => $tag,
            ];
            $remote = $_SERVER['REMOTE_ADDR'];
            if ($this->getOption('write_ip')) {
                $row['remote'] = $remote;
            }
            if ($this->getOption('write_timestamp')) {
                $row['timestamp'] = new DateTime();
            }
            if ($this->getOption('write_uri')) {
                $row['uri'] = $_SERVER['REQUEST_URI'];
            }
            $this->db->insert($this->getOption('table'), $row);
        } catch (\Exception $e) {
            $this->failed = true;

            throw $e;
        }
    }

    public function trace(): void {}
}
