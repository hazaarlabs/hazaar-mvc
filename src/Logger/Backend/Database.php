<?php

declare(strict_types=1);

namespace Hazaar\Logger\Backend;

use Hazaar\Date;
use Hazaar\DBI\Adapter;
use Hazaar\Logger\Backend;

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
            'driver' => $this->getOption('driver'),
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

    public function write(string $tag, string $message, int $level = LOG_NOTICE): void
    {
        if (true === $this->failed) {
            return;
        }

        try {
            $row = [
                'tag' => $tag,
                'message' => $message,
                'level' => $level,
            ];
            $remote = $_SERVER['REMOTE_ADDR'];
            if ($this->getOption('write_ip')) {
                $row['remote'] = $remote;
            }
            if ($this->getOption('write_timestamp')) {
                $row['timestamp'] = new Date();
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
