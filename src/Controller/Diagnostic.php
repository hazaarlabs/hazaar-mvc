<?php

declare(strict_types=1);

/**
 * @file        Controller/Error.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Controller;

use Hazaar\Application\Request;
use Hazaar\Application\Request\HTTP;

/**
 * @brief Basic controller class
 *
 * @detail This controller does basic stuff
 */
class Diagnostic extends Action
{
    protected int $code = 204;
    protected string $responseType = 'html';

    public function __initialize(?Request $request = null): ?Response
    {
        $response = parent::__initialize($request);
        if (getenv('HAZAAR_SID')) {
            $this->responseType = 'hazaar';
        } elseif (PHP_SAPI == 'cli') {
            $this->responseType = 'text';
        } elseif ($request instanceof HTTP) {
            if ($responseType = $this->application->getResponseType()) {
                $this->responseType = $responseType;
            } elseif ($x_requested_with = $request->getHeader('X-Requested-With')) {
                switch ($x_requested_with) {
                    case 'XMLHttpRequest':
                        $this->responseType = 'json';

                        break;

                    case 'XMLRPCRequest':
                        $this->responseType = 'xmlrpc';

                        break;
                }
            }
        } else {
            $this->responseType = 'text';
        }

        return $response;
    }

    final public function __run(): Response
    {
        if ($this->responseType && method_exists($this, $this->responseType)) {
            $response = call_user_func([$this, $this->responseType]);
        } elseif (method_exists($this, 'run')) {
            $response = $this->run();
        } else {
            $response = $this->html();
        }
        if (!$response instanceof Response) {
            if (is_array($response)) {
                $response = new Response\JSON($response, $this->code);
            } else {
                $response = new Response\HTML($response, $this->code);
                // Execute the action helpers.  These are responsible for actually rendering any views.
                // $this->_helpers->execAllHelpers($this, $response);
            }
        }
        $response->setController($this);

        return $response;
    }

    public function __shutdown(?Response $response = null): void {}

    public function json(): Response\JSON
    {
        $error = [
            'message' => 'NO CONTENT',
        ];

        return new Response\JSON($error, $this->code);
    }

    public function xmlrpc(): Response\XML
    {
        $xml = new \SimpleXMLElement('<xml/>');
        $xml->addChild('data', 'NO CONTENT');

        return new Response\XML($xml, $this->code);
    }

    public function html(): Response\HTML
    {
        return new Response\HTML('NO CONTENT', $this->code);
    }

    public function text(): Response\Text
    {
        return new Response\Text('NO CONTENT', $this->code);
    }

    public function hazaar(): void
    {
        http_response_code($this->code);
        echo "Hazaar Dump:\n\n";
        var_dump('NO CONTENT');
        echo "\n\n";
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        exit;
    }
}
