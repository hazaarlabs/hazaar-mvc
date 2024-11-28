<?php

declare(strict_types=1);

namespace Hazaar\Mail;

class TransportMessage
{
    /**
     * @var array<mixed>
     */
    public array $to = [];

    /**
     * @var array<mixed>
     */
    public array $cc = [];

    /**
     * @var array<mixed>
     */
    public array $bcc = [];

    /**
     * @var array<string>
     */
    public array $from = [];

    /**
     * @var array<string>
     */
    public array $replyTo = [];
    public string $subject = '';
    public Mime\Message|string $content;

    /**
     * @var array<mixed>
     */
    public array $headers = [];

    /**
     * @var array<string>
     */
    public array $dsn = [];

    /**
     * @var array<string>
     */
    public array $categories = [];

    /**
     * @var array<Attachment>
     */
    public array $attachments = [];
    public string $batch_id = '';
}
