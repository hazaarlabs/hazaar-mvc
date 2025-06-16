<?php

namespace Hazaar\Warlock\Enum;

/**
 * Enum representing different types of schedules.
 */
enum ScheduleType
{
    case CRON;
    case INTERVAL;
    case ONCE;
    case MANUAL;
    case DELAY;
    case NORM;
}
