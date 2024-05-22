<?php

namespace Hazaar\Mail\Interfaces;

use Hazaar\Mail\TransportMessage;

interface Transport
{
    public function send(TransportMessage $message): mixed;
}
