<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response\HTTP;

use Hazaar\Controller\Response;

/**
 * Represents a HTTP response for when the rate limit has been exceeded.
 * Extends the \Hazaar\Controller\Response class.
 */
class RateLimitExceeded extends Response
{
    public function __construct(string $content_type = 'text/html')
    {
        parent::__construct($content_type, 429);
    }
}
