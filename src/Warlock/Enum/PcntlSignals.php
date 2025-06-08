<?php

namespace Hazaar\Warlock\Enum;

enum PcntlSignals: int
{
    case SIGHUP = 1;    // Hangup detected on controlling terminal or death of controlling process
    case SIGINT = 2;    // Interrupt from keyboard
    case SIGQUIT = 3;   // Quit from keyboard
    case SIGTERM = 15;  // Termination signal
}
