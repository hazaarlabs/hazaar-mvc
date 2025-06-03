<?php

declare(strict_types=1);
use Hazaar\Application;
use Hazaar\Warlock\Agent\Struct\Endpoint;
use Hazaar\Warlock\Channel;
use Hazaar\Warlock\Connection\Pipe;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
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
        $this->state = Status::INIT;
    }

    /**
     * @param array<mixed> $argv
     */
    public function launch(array $argv): int
    {
        $this->log('Bootstrapping application', LogLevel::INFO);
        $this->application->bootstrap();
        $this->log('Application bootstrapped successfully', LogLevel::INFO);
        $this->send(PacketType::EXEC);
        $timeout = $this->config['timeout'] ?? 5;
        $start = time();
        while (Status::INIT === $this->state) {
            if (($start + $timeout) < time()) {
                $this->state = Status::ERROR;
                $this->log('Warlock Agent Runner timed out waiting for execution', LogLevel::ERROR);

                break;
            }
            $this->sleep(1, Status::INIT);
        }

        return match ($this->state) {
            Status::RUNNING => 0,
            default => 1
        };
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

    protected function processCommand(PacketType $command, ?stdClass $payload = null): bool
    {
        // Handle commands specific to the Warlock Agent Runner
        switch ($command) {
            case PacketType::EXEC:
                $this->state = Status::RUNNING;
                $this->log('Runner started successfully', LogLevel::INFO);
                $endpoint = Endpoint::create($payload->endpoint ?? null);
                if (!$endpoint) {
                    $this->log('Invalid endpoint provided for execution', LogLevel::ERROR);
                    $this->state = Status::ERROR;

                    return false;
                }
                Channel::registerConnection($this);

                try {
                    $endpoint->run($this->protocol);
                } catch (Throwable $e) {
                    $this->log('Error executing endpoint: '.$e->getMessage(), LogLevel::ERROR);
                    $this->state = Status::ERROR;

                    return false;
                }

                return true;

            case PacketType::CANCEL:
                $this->state = Status::STOPPING;
                $this->log('Runner stopped successfully', LogLevel::INFO);

                return true;

            default:
                $this->log('Unknown command received: '.$command->name, LogLevel::WARN);

                return false;
        }
    }
}

// Create application, bootstrap, and run
$application = new Application(APPLICATION_ENV);

exit(Runner::create($application, $argv));
