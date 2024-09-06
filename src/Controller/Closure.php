<?php

declare(strict_types=1);

namespace Hazaar\Controller;

use Hazaar\Application;
use Hazaar\Application\Route;
use Hazaar\Controller;
use Hazaar\Controller\Response\JSON;
use Hazaar\Controller\Response\Text;
use Hazaar\File;

class Closure extends Controller
{
    protected \Closure $closure;

    public function __construct(Application $application, \Closure $closure)
    {
        parent::__construct($application);
        $this->closure = $closure;
    }

    public function run(?Route $route = null): Response
    {
        $response = call_user_func($this->closure);
        if ($response instanceof Response) {
            return $response;
        }
        if ($response instanceof File) {
            return new Response\File($response);
        }
        if (is_array($response) || is_object($response)) {
            return new JSON($response);
        }

        return new Text($response);
    }
}
