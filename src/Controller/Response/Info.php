<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Application;

class Info extends View
{
    public function __construct()
    {
        $app = Application::getInstance();
        parent::__construct('@views/info', [
            'version' => HAZAAR_VERSION,
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS,
            'server' => $_SERVER['SERVER_SOFTWARE'],
            'request' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'timestamp' => date('Y-m-d H:i:s'),
            'memory' => [
                'usage' => memory_get_usage(),
                'peak' => memory_get_peak_usage(),
                'limit' => ini_get('memory_limit'),
            ],
            'time' => $app->timer->all(),
        ]);
    }
}
