<?php

declare(strict_types=1);

namespace Hazaar\HTTP;

use Hazaar\File;
use Hazaar\Map;
use Hazaar\XML\Element;

class WebDAV extends Client
{
    private bool $authorised = false;
    private Map $settings;

    /**
     * @var array<int,string>
     */
    private array $classes = [];

    /**
     * @var array<string>
     */
    private array $allow = [];

    /**
     * @param array<mixed> $settings
     */
    public function __construct(array|Map $settings)
    {
        parent::__construct();
        if (!$settings instanceof Map) {
            $settings = new Map($settings);
        }
        $this->settings = $settings;
        if (!$this->settings->has('baseuri')) {
            throw new \Exception('WebDAV: No baseuri specified');
        }
        $this->settings['baseuri'] = rtrim($this->settings['baseuri'], '/');
        if ($this->settings->has('username') && $this->settings->has('password')) {
            parent::auth($this->settings['username'], $this->settings['password']);
        }
        $options = $this->options('/');
        if (401 != $options->status) {
            if (200 != $options->status) {
                throw new \Exception('WebDAV server returned status '.$options->status.': '.$options->name);
            }
            if (!array_key_exists('dav', $options->headers)) {
                throw new \Exception('Base URI does not support WebDAV protocol.  URI='.$this->settings['baseuri']);
            }
            $this->classes = explode(',', $options->headers['dav'][0]);
            if (!in_array(1, $this->classes)) {
                throw new \Exception('Server must at least support WebDAV class 1!');
            }
            if (array_key_exists('allow', $options->headers)) {
                $this->allow = array_map('trim', explode(',', $options->headers['allow']));
            }
            $this->authorised = true;
        }
    }

    public function isAuthorised(): bool
    {
        return $this->authorised;
    }

    public function getAbsoluteUrl(string $url): string
    {
        if (preg_match('/\w\:\/\/.*/', $url)) {
            return $url;
        }

        return $this->settings['baseuri'].'/'.ltrim(implode('/', array_map('rawurlencode', explode('/', trim($url, '/')))), '/');
    }

    public function path(string $url): false|string
    {
        $expr = '/^'.preg_quote(trim($this->settings['baseuri'], '/'), '/').'\/(.*)/';
        if (preg_match($expr, $url, $matches)) {
            return '/'.trim($matches[1], '/');
        }

        return false;
    }

    /**
     * Get the contents of a file.
     *
     * @param string        $url             the URL of the file to get the contents of
     * @param array<mixed>  $properties      An array of properties to request.  If empty, all properties will be requested.
     * @param int           $depth           the depth of the PROPFIND request
     * @param bool          $return_response if true, the raw response object will be returned instead of the file contents
     * @param array<string> $namespaces      an array of namespaces to use in the PROPFIND request
     */
    public function propfind(string $url, array $properties = [], int $depth = 1, bool $return_response = false, array $namespaces = []): mixed
    {
        if (!in_array('PROPFIND', $this->allow)) {
            throw new \Exception('Host does not support PROPFIND command!');
        }
        if (!$this->authorised) {
            return false;
        }
        $request = new Request($this->getAbsoluteUrl($url), 'PROPFIND', 'application/xml; charset="utf-8"');
        $request->setHeader('Depth', (string) $depth);
        $request->setHeader('Prefer', 'return-minimal');
        $xml = new Element();
        $xml->addNamespace('d', 'DAV:');
        $propfind = $xml->add('d:propfind');
        if (count($namespaces) > 0) {
            foreach ($namespaces as $name => $uri) {
                $xml->addNamespace($name.':attr', $uri);
            }
        }
        if (count($properties) > 0) {
            $prop = $propfind->add('d:prop');
            foreach ($properties as $property) {
                if (false === strpos($property, ':')) {
                    $property = 'd:'.$property;
                }
                $prop->add($property);
            }
        } else {
            $propfind->add('d:allprop');
        }
        $request->setBody($xml->toXML());
        $response = parent::send($request);
        if (207 != $response->status) {
            throw new \Exception('WebDAV server returned: '.$response->body(), $response->status);
        }
        if ($return_response) {
            return $response;
        }
        $result = $this->parseMultiStatus($response->body);
        if (0 === $depth) {
            reset($result);
            $result = current($result);

            return isset($result[200]) ? $result[200] : [];
        }
        $newResult = [];
        foreach ($result as $href => $statusList) {
            $path = $this->path($href);
            $newResult[$path] = isset($statusList[200]) ? $statusList[200] : [];
        }

        return $newResult;
    }

    /**
     * @param array<mixed> $properties An array of properties to request.  If empty, all properties will be requested.
     */
    public function proppatch(string $url, array $properties): bool
    {
        if (!in_array('PROPPATCH', $this->allow)) {
            throw new \Exception('Host does not support PROPPATCH command!');
        }
        if (!$this->authorised) {
            return false;
        }
        $request = new Request($this->getAbsoluteUrl($url), 'PROPPATCH', 'application/xml; charset="utf-8"');
        $dom = new \DOMDocument();
        $dom->appendChild($propertyupdate = $dom->createElementNS('DAV:', 'd:propertyupdate'));
        $set = null;
        $remove = null;
        foreach ($properties as $key => $value) {
            if (null === $value) {
                if (!$remove instanceof \DOMElement) {
                    $propertyupdate->appendChild($remove = $dom->createElement('d:remove'));
                }
                $method = $remove;
            } else {
                if (!$set instanceof \DOMElement) {
                    $propertyupdate->appendChild($set = $dom->createElement('d:set'));
                }
                $method = $set;
            }
            $method->appendChild($prop = $dom->createElement('d:prop'));
            $prop->appendChild($dom->createElement($key, $value));
        }
        $request->setBody($dom->saveXML());
        $response = parent::send($request);
        if (207 != $response->status) {
            return false;
        }

        return true;
    }

    /**
     * Create a collection object (ie: a directory/folder).
     */
    public function mkcol(string $url): bool
    {
        if (!$this->authorised) {
            return false;
        }
        $request = new Request($this->getAbsoluteUrl($url), 'MKCOL');
        $result = parent::send($request);
        if (201 == $result->status) {
            return true;
        }

        return false;
    }

    /**
     * Create a new object (ie: a file);.
     */
    public function put(string $url, ?string $content = null, ?string $datatype = null): bool
    {
        if (!in_array('PUT', $this->allow)) {
            throw new \Exception('Host does not support PUT command!');
        }
        if (!$this->authorised) {
            return false;
        }
        $request = new Request($this->getAbsoluteUrl($url), 'PUT');
        $request->setHeader('Content-Type', $datatype);
        $request->setBody($content);
        $result = parent::send($request);
        if (201 == $result->status || 204 == $result->status) {
            return true;
        }

        return false;
    }

    public function putFile(string $dir_url, File $file): bool
    {
        if (!$this->authorised) {
            return false;
        }
        if (!$file->exists()) {
            return false;
        }
        if ('/' != substr($dir_url, -1, 1)) {
            $dir_url .= '/';
        }
        $url = $dir_url.$file->basename();

        return self::put($url, $file->getContents(), $file->mimeContentType());
    }

    public function delete(string|URL $url): false|Response
    {
        if (!in_array('DELETE', $this->allow)) {
            throw new \Exception('Host does not support DELETE command!');
        }
        if (!$this->authorised) {
            return false;
        }
        $request = new Request($this->getAbsoluteUrl($url), 'DELETE');
        $response = parent::send($request);
        if (204 === $response->status) {
            return $response;
        }

        return false;
    }

    public function xml(Element $xml): void
    {
        header('Content-type: application/xml');
        echo $xml->toXML();

        exit;
    }

    /**
     * @return array<mixed>
     */
    protected function parseProperties(Element $dom): array
    {
        $properties = [];
        if ($dom->count() > 0) {
            foreach ($dom->children() as $propNode) {
                $name = $propNode->getLocalName();
                if ($propNode->hasChildren()) {
                    $properties[$name] = $this->parseProperties($propNode);
                } else {
                    $properties[$name] = $propNode->value();
                }
            }
        }

        return $properties;
    }

    /**
     * @return array<mixed>
     */
    protected function parseMultiStatus(Element|string $xml): array
    {
        if ($xml instanceof Element) {
            $dom = $xml;
        } else {
            $dom = new Element();
            $dom->loadXML($xml);
        }
        $dom->setDefaultNamespace('DAV');
        $result = [];
        $responseList = $dom->child('response');
        if (!is_array($responseList)) {
            $responseList = [$responseList];
        }
        foreach ($responseList as $response) {
            if (!($href = rawurldecode($response->child('href')->value()))) {
                throw new \Exception('No HREF in multi-status response');
            }
            $propstat = $response->child('propstat');
            $status = $propstat->child('status')->value();
            list($ver, $status, $message) = explode(' ', $status, 3);
            settype($status, 'int');
            $result[$href][$status] = $this->parseProperties($propstat->child('prop'));
        }

        return $result;
    }
}
