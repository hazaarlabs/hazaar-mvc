<?php

namespace Hazaar\Controller\Response\HTTP;

/**
 * Represents a HTTP response for when the rate limit has been exceeded.
 * Extends the \Hazaar\Controller\Response class.
 */
class RateLimitExceeded extends \Hazaar\Controller\Response
{
    public function __construct($content_type = "text/html")
    {
        parent::__construct($content_type, 429);
    }

}
