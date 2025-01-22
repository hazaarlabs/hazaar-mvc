<?php

namespace Hazaar\Mail\Interface;

use Hazaar\Mail\TransportMessage;

interface Transport
{
    public function send(TransportMessage $message): mixed;
}
