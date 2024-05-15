<?php

namespace Hazaar\Mail\Transport;

interface _Interface
{
    /**
     * Send an email via the transport.
     *
     * @param array<array<string>|string> $to The email address to send the email to
     */
    public function send(
        array $to,
        ?string $subject = null,
        $message = null,
        array $headers = [],
        array $attachments = []
    ): bool;
}
