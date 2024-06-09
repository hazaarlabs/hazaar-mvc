<?php

declare(strict_types=1);

namespace Hazaar\File\Backend\Exception;

use Hazaar\HTTP\Response;

class SharePointError extends \Exception
{
    public Response $response;

    public function __construct(string $message, ?Response $response = null, int $code = 500)
    {
        $this->response = $response;

        parent::__construct($message, $code);
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
