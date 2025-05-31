<?php

namespace Hazaar\Warlock\Enum;

enum PacketType: int
{
    // SYSTEM MESSAGES
    case NOOP = 0x00;         // Null Opperation
    case INIT = 0x01;         // Initialise client
    case AUTH = 0x02;         // Authorise client
    case OK = 0x03;           // OK response
    case ERROR = 0x04;        // Error response
    case STATUS = 0x05;       // Status request/response
    case SHUTDOWN = 0x06;     // Shutdown request
    case PING = 0x07;         // Typical PING
    case PONG = 0x08;         // Typical PONG
    // CODE EXECUTION MESSAGES
    case DELAY = 0x10;        // Execute code after a period
    case SCHEDULE = 0x11;     // Execute code at a set time
    case EXEC = 0x12;         // Execute some code in the Warlock Runner.
    case CANCEL = 0x13;       // Cancel a pending code execution
    // SIGNALLING MESSAGES
    case SUBSCRIBE = 0x20;    // Subscribe to an event
    case UNSUBSCRIBE = 0x21;  // Unsubscribe from an event
    case TRIGGER = 0x22;      // Trigger an event
    case EVENT = 0x23;        // An event
    // SERVICE MESSAGES
    case ENABLE = 0x30;       // Start a service
    case DISABLE = 0x31;      // Stop a service
    case SERVICE = 0x32;      // Service status
    case SPAWN = 0x33;        // Spawn a dynamic service
    case KILL = 0x34;         // Kill a dynamic service instance
    case SIGNAL = 0x35;       // Signal between a dynamic service and its client
    // KV STORAGE MESSAGES
    case KVGET = 0x40;        // Get a value by key
    case KVSET = 0x41;        // Set a value by key
    case KVHAS = 0x42;        // Test if a key has a value
    case KVDEL = 0x43;        // Delete a value
    case KVLIST = 0x44;       // List all keys/values in the selected namespace
    case KVCLEAR = 0x45;      // Clear all values in the selected namespace
    case KVPULL = 0x46;        // Return and remove a key value
    case KVPUSH = 0x47;        // Append one or more elements on to the end of a list
    case KVPOP = 0x48;         // Remove and return the last element in a list
    case KVSHIFT = 0x49;       // Remove and return the first element in a list
    case KVUNSHIFT = 0x50;     // Prepend one or more elements to the beginning of a list
    case KVCOUNT = 0x51;       // Count number of elements in a list
    case KVINCR = 0x52;        // Increment an integer value
    case KVDECR = 0x53;        // Decrement an integer value
    case KVKEYS = 0x54;        // Return all keys in the selected namespace
    case KVVALS = 0x55;        // Return all values in the selected namespace
    // LOGGING/OUTPUT MESSAGES
    case LOG = 0x90;           // Generic log message
    case DEBUG = 0x91;

    public static function get(string $name): ?PacketType
    {
        $name = strtoupper($name);
        if (defined("self::{$name}")) {
            return constant("self::{$name}");
        }

        return null;
    }
}
