<?php

declare(strict_types=1);
use Hazaar\Application;
use Hazaar\Warlock\Connection\Pipe;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\Status;
use Hazaar\Warlock\Interface\Connection;
use Hazaar\Warlock\Process;
use Hazaar\Warlock\Protocol;

// Define path to application directory
define('APPLICATION_PATH', getenv('APPLICATION_PATH'));
define('APPLICATION_ENV', getenv('APPLICATION_ENV'));
define('APPLICATION_AUTOLOAD', getenv('APPLICATION_AUTOLOAD'));

if (!(APPLICATION_AUTOLOAD && file_exists(APPLICATION_AUTOLOAD))) {
    exit('Autoload file not found. Please set APPLICATION_AUTOLOAD environment variable to the path of your autoload.php file.');
}

// Composer autoloading
include APPLICATION_AUTOLOAD;

class Runner extends Process
{
    private Application $application;

    public function __construct(Application $application)
    {
        parent::__construct(new Protocol('1', false));
        $this->application = $application;
    }

    /**
     * @param array<mixed> $argv
     */
    public function launch(array $argv): int
    {
        $this->state = Status::RUNNING;
        $this->log('WARLOCK AGENT RUNNER STARTED', LogLevel::INFO);
        for ($i = 1; $i <= 30; ++$i) {
            $this->log('WARLOCK AGENT RUNNER COUNT='.$i, LogLevel::INFO);
            $this->sleep(1);
        }

        return 0; // Exit code 0 for success
    }

    /**
     * @param array<mixed> $argv
     */
    public static function create(Application $application, array $argv): int
    {
        $runner = new self($application);

        return $runner->launch($argv);
    }

    protected function createConnection(Protocol $protocol, ?string $guid = null): Connection|false
    {
        return new Pipe($protocol);
    }
}

// Create application, bootstrap, and run
$application = new Application(APPLICATION_ENV);

exit(Runner::create($application->bootstrap(), $argv));
