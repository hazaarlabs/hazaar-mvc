<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Components;

use parallel\Channel;
use parallel\Events;

class Test
{
    private bool $running = true;

    public function __construct() {}

    public function run(int $id): int
    {
        $events = new Events();
        $channel = Channel::open('test');
        $events->addChannel($channel);
        $events->setBlocking(true);
        echo "Starting test #{$id}".PHP_EOL;

        while ($this->running) {
            try {
                $event = $events->poll();

                if ($event && 'test' === $event->source) {
                    $events->addChannel($channel);

                    switch ($event->value[0]) {
                        case 'text':
                            echo "Received text on #{$id} with value: {$event->value[1]}".PHP_EOL;

                            break;
                    }
                }
            } catch (\Throwable $e) {
                echo "Error: {$e->getMessage()}".PHP_EOL;
                // Add a sleep on error to prevent tight loop
                sleep(1);
            }
        }

        return 0;
    }
}
