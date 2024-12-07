<?php

declare(strict_types=1);

namespace Hazaar\Xml\Rpc;

use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Controller;
use Hazaar\Controller\Response;
use Hazaar\Controller\Response\XML;
use Hazaar\XML\Element;
use Hazaar\XML\RPC\Exception\InvalidRequest;
use Hazaar\XML\RPC\Exception\MethodNotFound;

abstract class Server extends Controller
{
    protected Request $request;

    /**
     * @var array<string, array<object|string>>
     */
    protected array $registered_methods = [];

    public function __toString()
    {
        return get_class($this);
    }

    public function initialize(Request $request): ?Response
    {
        parent::initialize($request);
        $auto_register = true;
        if (method_exists($this, 'init')) {
            $auto_register = $this->init($request);
        }
        if (false !== $auto_register) {
            foreach (get_class_methods($this) as $method) {
                if ('run' == $method || preg_match('/^__/', $method)) {
                    continue;
                }
                $reflection = new \ReflectionMethod($this, $method);
                if ($reflection->isPublic()) {
                    $this->registerMethod($this, $method);
                }
            }
        }

        return null;
    }

    public function run(?Route $route = null): XML
    {
        $raw_post_data = file_get_contents('php://input');
        $method = null;
        $result = xmlrpc_decode_request($raw_post_data, $method);
        if (!$method) {
            throw new InvalidRequest($_SERVER['REMOTE_ADDR']);
        }
        if (!array_key_exists($method, $this->registered_methods)) {
            throw new MethodNotFound($method);
        }
        $response = call_user_func_array($this->registered_methods[$method], $result);
        $xml = new Element();
        $xml->loadXML(\xmlrpc_encode_request($method, $response));

        return new XML($xml);
    }

    public function registerMethod(object $object, string $method): void
    {
        $this->registered_methods[$method] = [
            $object,
            $method,
        ];
    }
}
