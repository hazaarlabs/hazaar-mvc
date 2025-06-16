<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Enum;

enum ExitCode: int
{
    case NORMAL = 0;
    case DECODE_START_PAYLOAD = 1;
    case START_PAYLOAD_TYPE = 2;
    case SERVICE_CLASS_NOT_EXIST = 3;
    case LOST_CONTROL_CHANNEL = 4;
    case DYNAMIC_SERVICE_RUN_ONCE = 5;
    case SOURCE_FILE_MODIFIED = 6;
    case EXCEPTION = 7;

    public function toString(): string
    {
        return match ($this) {
            self::NORMAL => 'Service exited normally.',
            self::DECODE_START_PAYLOAD => 'Service failed to start because the application failed to decode the start payload.',
            self::START_PAYLOAD_TYPE => 'Service failed to start because the application runner does not understand the start payload type.',
            self::SERVICE_CLASS_NOT_EXIST => 'Service failed to start because service class does not exist.',
            self::LOST_CONTROL_CHANNEL => 'Service exited because it lost the control channel.',
            self::DYNAMIC_SERVICE_RUN_ONCE => 'Dynamic service failed to start because it has no runOnce() method!',
            self::SOURCE_FILE_MODIFIED => 'Service exited because it\'s source file was modified.',
            self::EXCEPTION => 'Service exited due to an exception.'
        };
    }
}
