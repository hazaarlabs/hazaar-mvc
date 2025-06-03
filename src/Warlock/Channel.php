<?php

namespace Hazaar\Warlock;

/**
 * The Channel class manages a collection of connections to the Warlock server.  The purpose of this class is to
 * provide a way to trigger events across all connected channels, allowing for communication and coordination
 * between different parts of the application that are using Warlock.
 *
 * By providing a static method to register connections and trigger events, the Channel class simplifies the process
 * and allows anonymous functions or other callable methods to be executed across all active connections.
 *
 * ## Example use from a Closure:
 * ```php
 * use Hazaar\Warlock\Channel;
 * use Hazaar\Warlock\Client;
 *
 * function() {
 *   $client = new Client();
 *   $client->connect();
 *   $client->exec(function(){
 *     Channel::trigger('eventName', 'data');
 *   });
 * }
 * ```
 */
class Channel
{
    /**
     * @var array<Process>
     */
    public static array $connections = [];

    public static function registerConnection(
        Process $process
    ): void {
        self::$connections[] = $process;
    }

    /**
     * Triggers an event on all connected channels.
     *
     * Iterates through all active connections and triggers the specified event with the provided data.
     * Skips any connections that are not currently connected.
     *
     * @param string $event the name of the event to trigger
     * @param mixed  $data  optional data to pass along with the event
     *
     * @return bool returns true after attempting to trigger the event on all connections
     */
    public static function trigger(string $event, mixed $data = null): bool
    {
        foreach (self::$connections as $connection) {
            if (!$connection->connected()) {
                continue;
            }
            $connection->trigger($event, $data);
        }

        return true;
    }
}
