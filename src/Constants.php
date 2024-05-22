<?php

declare(strict_types=1);

// Logging Levels
define('W_INFO', 0);
define('W_ERR', 1);
define('W_WARN', 2);
define('W_NOTICE', 3);
define('W_DEBUG', 4);
define('W_DECODE', 5);
define('W_DECODE2', 6);

// Service Status Codes
define('HAZAAR_SERVICE_NONE', null);
define('HAZAAR_SERVICE_ERROR', -1);
define('HAZAAR_SERVICE_INIT', 0);
define('HAZAAR_SERVICE_READY', 1);
define('HAZAAR_SERVICE_RUNNING', 2);
define('HAZAAR_SERVICE_SLEEP', 3);
define('HAZAAR_SERVICE_STOPPING', 4);
define('HAZAAR_SERVICE_STOPPED', 5);
define('HAZAAR_SCHEDULE_DELAY', 0);
define('HAZAAR_SCHEDULE_INTERVAL', 1);
define('HAZAAR_SCHEDULE_NORM', 2);
define('HAZAAR_SCHEDULE_CRON', 3);
