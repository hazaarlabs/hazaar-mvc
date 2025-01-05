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
use Hazaar\Application\Route;
use Hazaar\XML\Element;

/**
 * @brief Basic controller class
 *
 * @detail This controller does basic stuff
 */
class Diagnostic extends Action
{
    protected int $code = 204;
    protected int $responseType = Response::TYPE_HTML;

    /**
     * @var array<mixed>
     */
    protected array $caller = [];

    /**
     * Initializes the controller with the given request and determines the response type.
     *
     * This method overrides the parent initialize method to set the response type based on
     * various conditions such as environment variables, the PHP SAPI, and request headers.
     *
     * @param Request $request the request object, or null if not available
     *
     * @return null|Response the response object, or null if not available
     */
    public function initialize(Request $request): ?Response
    {
        $response = parent::initialize($request);
        if (getenv('HAZAAR_SID')) {
            $this->responseType = Response::TYPE_HAZAAR;
        } elseif (PHP_SAPI == 'cli') {
            $this->responseType = Response::TYPE_TEXT;
        } elseif ($x_requested_with = $request->getHeader('X-Requested-With')) {
            switch ($x_requested_with) {
                case 'XMLHttpRequest':
                    $this->responseType = Response::TYPE_JSON;

                    break;

                case 'XMLRPCRequest':
                    $this->responseType = Response::TYPE_XML;

                    break;
            }
        } else {
            $this->responseType = Response::TYPE_HTML;
        }

        return $response;
    }

    /**
     * @param array<mixed> $caller
     */
    public function setCaller(array $caller): void
    {
        $this->caller = $caller;
    }

    /**
     * Executes the diagnostic run process.
     *
     * This method determines the appropriate response type based on the presence of a response type method
     * or the existence of a 'run' method. If neither is found, it defaults to an HTML response.
     * The response is then wrapped in the appropriate Response object if it is not already an instance of Response.
     *
     * @param null|Route $route the route object, which may be null
     *
     * @return Response the response object, which can be of type JSON or HTML
     */
    final public function run(?Route $route = null): Response
    {
        if ($this->responseType && method_exists($this, $method = Response::getResponseTypeName($this->responseType))) {
            $response = call_user_func([$this, $method], $this->caller);
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

        return $response;
    }

    /**
     * Returns a JSON response with an error message.
     *
     * @return Response\JSON the JSON response containing the error message
     */
    public function json(): Response\JSON
    {
        $error = [
            'message' => 'NO CONTENT',
        ];

        return new Response\JSON($error, $this->code);
    }

    /**
     * Generates an XML-RPC response.
     *
     * This method creates a SimpleXMLElement with a root element `xml` and adds a child element `data` with the content 'NO CONTENT'.
     * It then returns this XML structure wrapped in a Response\XML object.
     *
     * @return Response\XML the XML response object containing the generated XML structure
     */
    public function xml(): Response\XML
    {
        $xml = new Element();
        $xml->add('data', 'NO CONTENT');

        return new Response\XML($xml, $this->code);
    }

    /**
     * Generates an HTML response with a predefined message.
     *
     * @return Response\HTML the HTML response containing 'NO CONTENT' and the specified status code
     */
    public function html(): Response\HTML
    {
        return new Response\HTML('NO CONTENT', $this->code);
    }

    /**
     * Returns a text response with the content 'NO CONTENT' and the specified status code.
     *
     * @return Response\Text the text response object
     */
    public function text(): Response\Text
    {
        return new Response\Text('NO CONTENT', $this->code);
    }

    /**
     * Outputs a diagnostic dump and terminates the script.
     *
     * This method sets the HTTP response code to the value of the `$code` property,
     * outputs a diagnostic message, dumps a placeholder string 'NO CONTENT',
     * prints a backtrace, and then exits the script.
     */
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
