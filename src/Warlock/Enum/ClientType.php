<?php

namespace Hazaar\Warlock\Enum;

enum ClientType: string
{
    case BASIC = 'basic';
    case ADMIN = 'admin';
    case AGENT = 'agent';
    case PEER = 'peer';
}
