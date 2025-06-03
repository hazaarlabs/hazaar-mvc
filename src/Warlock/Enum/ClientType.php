<?php

namespace Hazaar\Warlock\Enum;

enum ClientType: string
{
    case BASIC = 'basic';
    case USER = 'user';
    case ADMIN = 'admin';
    case AGENT = 'agent';
    case PEER = 'peer';
}
