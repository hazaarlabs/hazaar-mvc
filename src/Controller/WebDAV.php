<?php

declare(strict_types=1);

namespace Hazaar\Controller;

use Hazaar\Application\Request;
use Hazaar\Application\Request\HTTP;
use Hazaar\Controller\Response\HTTP\OK;
use Hazaar\Controller\Response\XML;
use Hazaar\File;
use Hazaar\File\Dir;
use Hazaar\File\Manager;
use Hazaar\XML\Element;

abstract class WebDAV extends Basic
{
    /**
     * @var HTTP
     */
    protected Request $request;
    protected Manager $manager;
    private string $__propset = '<http://apache.org/dav/propset/fs/1>';

    /**
     * @var array<int>
     */
    private array $__dav_classes = [1];

    /**
     * @var array<string>
     */
    private array $__allowed_methods = [
        'OPTIONS',
        'GET',
        'HEAD',
        'POST',
        'DELETE',
        'TRACE',
        'PROPFIND',
        'PROPPATCH',
        'COPY',
        'MOVE',
        'LOCK',
    ];

    public function runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): Response
    {
        if (true === $namedActionArgs) {
            throw new \Exception('Named action arguments are not supported for WebDAV actions.');
        }
        if ('index' !== $actionName) {
            if (($this->manager = Manager::select($actionName)) === false) {
                throw new \Exception('Unknown media source!', 404);
            }
        }
        $method = strtolower($this->request->getMethod());
        // If the method is not supported, check for a __default handler to pass it off to, or else 405.
        if (!(in_array(strtoupper($method), $this->__allowed_methods) && method_exists($this, $method))) {
            if (!method_exists($this, '__default')) {
                throw new \Exception('Method not supported', 405);
            }

            return $this->__default($this->name, $actionName);
        }
        $response = call_user_func([$this, $method]);
        if ($this->stream) {
            $response = new Response\Stream($response);
        }
        if (!$response instanceof Response) {
            throw new \Exception('Internal Server Error', 500);
        }

        return $response;
    }

    public function options(): Response
    {
        $response = new OK();
        $response->setContentType('httpd/unix-directory');
        $response->setHeader('DAV', implode(',', $this->__dav_classes));
        $response->setHeader('DAV', $this->__propset, false);
        // Only allow methods that are listed and also have a callable function
        $methods = array_intersect(array_map('strtoupper', get_class_methods($this)), $this->__allowed_methods);
        $response->setHeader('Allow', implode(',', $methods));

        return $response;
    }

    public function propfind(): Response
    {
        $path = '/'.ltrim($this->request->getPath(), '/');
        $depth = $this->request->hasHeader('Depth') ? $this->request->getHeader('Depth') : 'infinity';
        if ('infinity' === strtolower($depth)) {
            throw new \Exception('PROPFIND requests with a Depth of "infinity" are not allowed for '.$path.'.');
        }
        $depth = (int) $depth;
        if (0 !== $depth && 1 !== $depth) {
            throw new \Exception('Bad request', 400);
        }
        date_default_timezone_set('UTC');
        $object = $this->manager->get($path);
        $xml = new Element();
        $xml->addNamespace('D', 'DAV:');
        $ms = $xml->add('D:multistatus');
        $this->writeObjectStatusResponse($object, $ms);
        if (1 === $depth && $object instanceof Dir) {
            while ($child = $object->read()) {
                $this->writeObjectStatusResponse($child, $ms);
            }
        }
        $response = new XML($xml);
        $response->setStatus(207);

        return $response;
    }

    public function proppatch(): Response
    {
        throw new \Exception(__METHOD__.' not currently implemented!', 403);
    }

    public function lock(): Response
    {
        throw new \Exception(__METHOD__.' not currently implemented!', 403);
    }

    public function post(): Response
    {
        throw new \Exception(__METHOD__.' not currently implemented!', 403);
    }

    public function copy(): Response
    {
        throw new \Exception(__METHOD__.' not currently implemented!', 403);
    }

    public function move(): Response
    {
        throw new \Exception(__METHOD__.' not currently implemented!', 403);
    }

    public function delete(): Response
    {
        throw new \Exception(__METHOD__.' not currently implemented!', 403);
    }

    public function trace(): Response
    {
        throw new \Exception(__METHOD__.' not currently implemented!', 403);
    }

    private function writeObjectStatusResponse(Dir|File $object, Element $xml): Element
    {
        $response = $xml->add('D:response');
        $response->addNamespace('lp1', 'DAV:');
        $response->addNamespace('lp2', 'http://apache.org/dav/props/');
        $response->add('D:href', $object->fullpath());
        $propstat = $response->add('D:propstat');
        $prop = $propstat->add('D:prop');
        $type = $prop->add('lp1:resourcetype');
        if ($object instanceof Dir) {
            $type->add('D:collection');
        }
        $prop->add('lp1:creationdate', date('c', $object->ctime()));
        $prop->add('lp1:getlastmodified', date('c', $object->mtime()));
        if ($object instanceof File) {
            $prop->add('lp1:getcontentlength', $object->size());
            $prop->add('lp2:executable', 'F');
        }
        $prop->add('lp1:getetag', '14-5959eb7f60805');
        $prop->add('D:supportedlock');
        $prop->add('D:getcontenttype', $object->mimeContentType());
        $propstat->add('D:status', $object->exists() ? 'HTTP/1.1 200 OK' : 'HTTP/1.1 404 Not Found');

        return $response;
    }
}
