<?php

declare(strict_types=1);

namespace Hazaar\File\Backend\Exception;

use Hazaar\Exception;
use Hazaar\HTTP\Response;

class SharePointError extends Exception
{
    public Response $response;

    public function __construct($message, Response $response = null, $code = 500)
    {
        $this->response = $response;

        parent::__construct($message, $code);
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
