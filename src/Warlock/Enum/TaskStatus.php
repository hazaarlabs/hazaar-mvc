<?php

namespace Hazaar\Warlock\Enum;

enum TaskStatus
{
    // STATUS CONSTANTS
    case INIT;
    case QUEUED;
    case RESTART;
    case STARTING;
    case RUNNING;
    case COMPLETE;
    case CANCELLED;
    case ERROR;
    case RETRY;
    case WAIT;
}
