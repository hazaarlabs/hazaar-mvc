<?php

declare(strict_types=1);

namespace Hazaar\File\Backend;

use Hazaar\Cache;
use Hazaar\File\Manager;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;
use Hazaar\HTTP\Response;
use Hazaar\Map;

class SharePoint extends Client implements Interfaces\Backend, Interfaces\Driver
{
    public string $separator = '/';

    protected Manager $manager;
    private Map $options;
    private Cache $cache;
    private static string $STSAuthURL = 'https://login.microsoftonline.com/extSTS.srf';
    private static string $signInURL = '/_forms/default.aspx?wa=wsignin1.0';
    private ?string $requestFormDigest;
    private \stdClass $root;

    /**
     * @var array<string,mixed>
     */
    private array $hostInfo;

    /**
     * SharePoint constructor.
     *
     * @param array<string,mixed>|Map $options
     */
    public function __construct(array|Map $options, Manager $manager)
    {
        $this->manager = $manager;
        parent::__construct();
        $this->disableRedirect();
        $this->options = new Map([
            'webURL' => null,
            'username' => null,
            'password' => null,
            'root' => 'Shared Documents',
            'cache_backend' => 'file',
            'direct' => false,
        ], $options);
        if (null === $this->options['webURL'] || null === $this->options['username'] || null === $this->options['password']) {
            throw new Exception\SharePointError('SharePoint filesystem backend requires a webURL, username and password.');
        }
        $cache_options = [
            'use_pragma' => false,
            'namespace' => 'sharepoint_'.md5($this->options['username'].':'.$this->options['password'].'@'.$this->options['webURL']),
        ];
        $this->cache = new Cache($this->options['cache_backend'], $cache_options);
        $this->uncacheCookie($this->cache);
        $this->root = new \stdClass();
        $this->hostInfo = parse_url($this->options['webURL']);
        // Forces loading the root folder
        // $this->info('/');
    }

    public function __destruct() {}

    public static function label(): string
    {
        return 'Microsoft SharePoint';
    }

    public function reload(): bool
    {
        return true;
    }

    public function reset(): bool
    {
        return true;
    }

    public function authorise(?string $redirect_uri = null): bool
    {
        if ($this->authorised()) {
            return true;
        }
        if (!($token = $this->getSecurityToken($this->options['username'], $this->options['password']))) {
            throw new Exception\SharePointError('Unable to get SharePoint security token!');
        }

        return $this->getAuthenticationCookies($token);
    }

    public function authorised(): bool
    {
        return $this->hasCookie('FedAuth') && $this->hasCookie('rtFa');
    }

    public function refresh(bool $reset = false): bool
    {
        return true;
    }

    /**
     * @return array<string>|bool
     */
    public function scandir(
        string $path,
        ?string $regex_filter = null,
        int $sort = SCANDIR_SORT_ASCENDING,
        bool $show_hidden = false,
        ?string $relative_path = null
    ): array|bool {
        $files = [];
        if ($info = &$this->info($path)) {
            if (!(isset($info->items, $info->ItemCount)) || $info->ItemCount !== count($info->items)) {
                $info->items = $this->load($path);
            }
            foreach ($info->items as $item) {
                $files[] = $item->Name;
            }
        }

        return $files;
    }

    // Check if file/path exists
    public function exists(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return ake($info, 'Exists', false);
    }

    public function realpath(string $path): ?string
    {
        if (!($info = $this->info($path))) {
            return null;
        }

        return $info->ServerRelativeUrl;
    }

    public function isReadable(string $path): bool
    {
        if ($this->info($path)) {
            return true;
        }

        return false;
    }

    public function isWritable(string $path): bool
    {
        if ($this->info($path)) {
            return true;
        }

        return false;
    }

    // TRUE if path is a directory
    public function isDir(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return 'SP.Folder' === $info->__metadata->type;
    }

    // TRUE if path is a symlink
    public function isLink(string $path): bool
    {
        return false;
    }

    // TRUE if path is a normal file
    public function isFile(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return 'SP.File' === $info->__metadata->type;
    }

    // Returns the file type
    public function filetype(string $path): false|string
    {
        if (!($info = $this->info($path))) {
            return false;
        }
        $types = ['SP.Folder' => 'dir', 'SP.File' => 'file'];

        return ake($types, $info->__metadata->type, false);
    }

    // Returns the file modification time
    public function filectime(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return strtotime($info->TimeCreated);
    }

    // Returns the file modification time
    public function filemtime(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return strtotime($info->TimeLastModified);
    }

    public function touch(string $path): bool
    {
        return false;
    }

    // Returns the file modification time
    public function fileatime(string $path): false|int
    {
        return time();
    }

    public function filesize(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return (int)ake($info, 'Length', 0);
    }

    public function fileperms(string $path): false|int
    {
        return false;
    }

    public function chmod(string $path, int $mode): bool
    {
        return false;
    }

    public function chown(string $path, string $user): bool
    {
        return false;
    }

    public function chgrp(string $path, string $group): bool
    {
        return false;
    }

    public function cwd(): string
    {
        return $this->separator;
    }

    public function unlink(string $path): bool
    {
        $url = $this->_objectURL($path);
        $result = $this->_query($url, 'POST', null, ['X-HTTP-Method' => 'DELETE']);

        return false !== $result;
    }

    public function mimeContentType(string $path, ?string $hint = null): ?string
    {
        if ($type = Manager::lookupContentType(pathinfo($path, PATHINFO_EXTENSION))) {
            return $type;
        }

        return null;
    }

    public function md5Checksum(string $path): ?string
    {
        if (!($info = $this->info($path))) {
            return null;
        }

        return md5($info->ContentTag);
    }

    // Create a directory
    public function mkdir(string $path): bool
    {
        $url = $this->_web('folders');
        $body = [
            '__metadata' => [
                'type' => 'SP.Folder',
            ],
            'ServerRelativeUrl' => $this->options['root'].$path,
        ];
        $result = $this->_query($url, 'POST', $body);

        return $this->updateInfo($result);
    }

    public function rmdir(string $path, bool $recurse = false): bool
    {
        $url = $this->_folder($path);
        $this->_query($url, 'POST', null, ['X-HTTP-Method' => 'DELETE']);

        return true;
    }

    // Copy a file from src to dst
    public function copy(string $src, string $dst, bool $recursive = false): bool
    {
        $dst = parse_url($this->options['webURL'], PHP_URL_PATH).'/'.$this->resolvePath($dst);
        $url = $this->_objectURL($src)."/copyTo('{$dst}')";
        $result = $this->_query($url, 'POST');

        return $result instanceof \stdClass && \property_exists($result, 'd') && \property_exists($result->d, 'CopyTo');
    }

    public function link(string $src, string $dst): bool
    {
        return false;
    }

    // Move a file from src to dst
    public function move(string $src, string $dst): bool
    {
        $dstInfo = $this->info($dst);
        $dst = parse_url($this->options['webURL'], PHP_URL_PATH).'/'.$this->resolvePath($dst);
        if ('SP.Folder' === ake($dstInfo, '__metadata.type')) {
            $dst .= '/'.$this->encodePath($src);
        }
        $url = $this->_objectURL($src)."/moveTo('{$dst}')";
        $result = $this->_query($url, 'POST');

        return $result instanceof \stdClass && \property_exists($result, 'd') && \property_exists($result->d, 'MoveTo');
    }

    // Read the contents of a file
    public function read(string $path, int $offset = -1, ?int $maxlen = null): false|string
    {
        return $this->_query($this->_folder(dirname($path))."/Files('".$this->encodePath($path)."')/\$value");
    }

    // Write the contents of a file
    public function write(string $file, string $data, ?string $content_type = null, bool $overwrite = false): ?int
    {
        $url = $this->_folder(dirname($file), "Files/add(url='".$this->encodePath($file)."',overwrite=".strbool($overwrite).')');
        $result = $this->_query($url, 'POST', $data, null, $response);
        if ($this->updateInfo($result)) {
            return strlen($data);
        }

        return null;
    }

    public function upload(string $path, array $file, bool $overwrite = false): bool
    {
        return $this->write(rtrim($path, ' /').'/'.$file['name'], file_get_contents($file['tmp_name']), null, $overwrite) > 0;
    }

    /**
     * @param array<string,int|string> $values
     */
    public function setMeta(string $path, array $values): bool
    {
        return false;
    }

    public function getMeta(string $path, ?string $key = null): array|false|string
    {
        return false;
    }

    /**
     * @param array<string,int|string> $params
     */
    public function previewURL(string $path, array $params = []): false|string
    {
        return false;
    }

    public function directURL(string $path): false|string
    {
        if (true !== $this->options['direct'] || !($info = $this->info($path))) {
            return false;
        }
        if ('SP.Folder' === $info->__metadata->type) {
            return false;
        }

        return $info->LinkingUri;
    }

    public function buildAuthURL(?string $callback_url = null): ?string
    {
        return null;
    }

    public function thumbnailURL(string $path, int $width = 100, int $height = 100, string $format = 'jpeg', array $params = []): false|string
    {
        return false;
    }

    public function openStream(string $path, string $mode): mixed
    {
        return false;
    }

    /**
     * @param resource $stream
     */
    public function writeStream($stream, string $bytes, ?int $length = null): int
    {
        return -1;
    }

    /**
     * @param resource $stream
     */
    public function readStream($stream, int $length): false|string
    {
        return false;
    }

    /**
     * @param resource $stream
     */
    public function seekStream(mixed $stream, int $offset, int $whence = SEEK_SET): int
    {
        return -1;
    }

    /**
     * @param resource $stream
     */
    public function tellStream(mixed $stream): int
    {
        return -1;
    }

    /**
     * @param resource $stream
     */
    public function eofStream(mixed $stream): bool
    {
        return true;
    }

    /**
     * @param resource $stream
     */
    public function truncateStream(mixed $stream, int $size): bool
    {
        return false;
    }

    /**
     * @param resource $stream
     */
    public function lockStream(mixed $stream, int $operation, ?int &$wouldblock = null): bool
    {
        return false;
    }

    /**
     * @param resource $stream
     */
    public function flushStream(mixed $stream): bool
    {
        return false;
    }

    /**
     * @param resource $stream
     */
    public function getsStream(mixed $stream, ?int $length = null): false|string
    {
        return false;
    }

    /**
     * @param resource $stream
     */
    public function closeStream($stream): bool
    {
        return false;
    }

    private function encodePath(string $value): string
    {
        return rawurlencode(basename(str_replace("'", "''", $value)));
    }

    private function getSecurityToken(string $username, string $password): bool|string
    {
        $xmlFile = __DIR__.DIRECTORY_SEPARATOR.'XML'.DIRECTORY_SEPARATOR.'SAML.xml';
        if (!file_exists($xmlFile)) {
            throw new Exception\SharePointError('SAML XML authorisation template is missing!');
        }
        $request = new Request(self::$STSAuthURL, 'POST');
        $request->setHeader('Accept', 'application/json; odata=verbose');
        $template = file_get_contents($xmlFile);
        $template = str_replace('{username}', $this->options['username'], $template);
        $template = str_replace('{password}', $this->options['password'], $template);
        $template = str_replace('{address}', $this->options['webURL'], $template);
        $request->setBody($template);
        $response = $this->send($request);
        if (200 !== $response->status) {
            throw new Exception\SharePointError('Invalid response requesting security token.', $response);
        }
        $xml = new \DOMDocument();
        $xml->loadXML($response->body());
        if (!$xml instanceof \DOMDocument) {
            throw new Exception\SharePointError('Invalid response authenticating SharePoint access.', $response);
        }
        $xpath = new \DOMXPath($xml);
        if ($xpath->query('//wsse:BinarySecurityToken')->length > 0) {
            $nodeToken = $xpath->query('//wsse:BinarySecurityToken')->item(0);
            if (!empty($nodeToken)) {
                return $nodeToken->nodeValue;
            }
        }
        if ($xpath->query('//S:Fault')->length > 0) {
            throw new Exception\SharePointError($xpath->query('//S:Fault')->item(0)->nodeValue);
        }

        return false;
    }

    private function getAuthenticationCookies(string $token): bool
    {
        $url_info = parse_url($this->options['webURL']);
        $url = $url_info['scheme'].'://'.$url_info['host'].self::$signInURL;
        $request = new Request($url, 'POST');
        $request->setBody($token);
        $response = $this->send($request, 0);
        if (302 !== $response->status) {
            throw new Exception\SharePointError('Invalid response requesting auth cookies: '.$response->status);
        }
        $this->deleteCookie('fpc');
        $this->deleteCookie('x-ms-gateway-slice');
        $this->deleteCookie('stsservicecookie');
        $this->deleteCookie('RpsContextCookie');
        $this->cacheCookie($this->cache);

        return true;
    }

    private function resolvePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', trim($this->options['root'].'/'.ltrim($path, ' /'), '/'))));
    }

    private function _getFormDigest(): string
    {
        if (!$this->requestFormDigest) {
            $this->authorise();
            $request = new Request($this->options['webURL'].'/_api/contextinfo', 'POST');
            $request->setHeader('Accept', 'application/json; OData=verbose');
            $response = $this->send($request);
            $this->requestFormDigest = ake($response->body(), 'd.GetContextWebInformation.FormDigestValue');
        }

        return $this->requestFormDigest;
    }

    /**
     * @param array<mixed>         $body
     * @param array<string,string> $extra_headers
     */
    private function _query(
        string $url,
        string $method = 'GET',
        null|array|string $body = null,
        ?array $extra_headers = null,
        ?Response &$response = null
    ): false|\stdClass {
        $retries = 3;
        for ($i = 0; $i < $retries; ++$i) {
            try {
                $this->authorise();
                if ('POST' === $method || 'PUT' === $method) {
                    $extra_headers['X-RequestDigest'] = $this->_getFormDigest();
                }
                $request = new Request($url, $method, 'application/json; OData=verbose');
                $request->setURLEncode(false);
                $request->setHeader('Accept', 'application/json; OData=verbose');
                if (is_array($extra_headers)) {
                    foreach ($extra_headers as $key => $value) {
                        $request->setHeader($key, $value);
                    }
                }
                if (null !== $body) {
                    $request->setBody(is_string($body) ? $body : json_encode($body));
                }
                $response = $this->send($request);
            } catch (\Exception $e) {
                throw new Exception\Offline();
            }
            if (in_array($response->status, [200, 201])) {
                return $response->body();
            }
            if (in_array($response->status, [401, 403])) {
                $this->requestFormDigest = null;
                if (401 === $response->status) {
                    $this->deleteCookies();
                }

                continue;
            }
            if (500 === $response->status) {
                return false;
            }
            $exception_message = ($error = ake($response->body(), 'error'))
                ? 'Invalid response ('.$response->status.') from SharePoint: code='.$error->code.' message='.$error->message->value
                : 'Unknown response: '.$response->body();

            throw new Exception\SharePointError($exception_message, $response);
        }

        throw new Exception\SharePointError('Query failed after '.$retries.' retries with code '.$response->status.'.  Giving up!');
    }

    private function _objectURL(string $path): false|string
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return ('SP.Folder' === $info->__metadata->type)
            ? $this->_folder($path)
            : $this->_folder(dirname($path))."/Files('".$this->encodePath($path)."')";
    }

    private function _web(?string $path = null): string
    {
        return $this->options['webURL'].'/_api/Web'.($path ? '/'.ltrim($path) : '');
    }

    private function _folder(?string $path = null, ?string $suffix = null): string
    {
        if ($path = $this->resolvePath($path)) {
            $path = "GetFolderByServerRelativeUrl('{$path}')";
        }
        $url = $this->_web($path);
        if (null !== $suffix) {
            $url .= '/'.$suffix;
        }

        return $url;
    }

    private function &info(string $path, ?\stdClass $new_item = null): ?\stdClass
    {
        $folder = &$this->root;
        $parts = explode('/', trim($path, ' /'));
        // If there's no item, we're loading the root, so query for the root
        if (!($item = array_pop($parts)) && !property_exists($folder, 'Name')) {
            if (!($folder = ake($this->_query($this->_folder('/')), 'd'))) {
                if (!$this->mkdir('/')) {
                    throw new Exception\SharePointError('Root folder does not exist and could not be created automatically!');
                }
                $folder = &$this->root;
            }
        }
        foreach ($parts as $part) {
            if (!property_exists($folder, 'items')) {
                $folder->items = [];
            }
            if (!array_key_exists($part, $folder->items)) {
                $folder->items[$part] = (object) ['Name' => $part];
            }
            $folder = &$folder->items[$part];
        }
        if (!$item) {
            return $folder;
        }

        try {
            if (!($folder instanceof \stdClass && property_exists($folder, 'Exists'))) {
                $folder = (object) array_merge((array) ake($this->_query($this->_folder(implode('/', $parts))), 'd'), (array) $folder);
            }
            if (ake($folder, 'Exists')) {
                if (!property_exists($folder, 'items')) {
                    $folder->items = $this->load(implode('/', $parts));
                }
                foreach ($folder->items as &$f) {
                    if ($f->Name === $item) {
                        if (null !== $new_item) {
                            $f = $new_item;
                        }

                        return $f;
                    }
                }
                if (null !== $new_item) {
                    $folder->items[] = $new_item;

                    return $new_item;
                }
            }
        } catch (Exception\SharePointError $e) {
            if (404 !== $e->response->status) {
                throw $e;
            }
        }
        $null = null;

        return $null;
    }

    private function updateInfo(\stdClass $info): bool
    {
        if (property_exists($info, 'd')) {
            $info = $info->d;
        }
        $folder = &$this->root;
        $name = str_ireplace($this->hostInfo['path'].'/'.ltrim($this->options['root'], '/'), '', $info->ServerRelativeUrl);
        if ('' === $name) {
            $this->root = $info;
        } else {
            $parts = explode('/', trim(dirname($name), '/ '));
            // Update any existing file metadata.  Here, if the metadata has not been loaded then we don't need to update.
            foreach ($parts as $part) {
                if (!$part) {
                    continue;
                }
                if (!(property_exists($folder, 'items') && array_key_exists($part, $folder->items))) {
                    break;
                }
                $folder = &$folder->items[$part];
            }
            $key = basename($name);
            if ($folder instanceof \stdClass && property_exists($folder, 'items')) {
                $folder->items[$key] = $info;
            }
        }

        return true;
    }

    /**
     * @return array<mixed>
     */
    private function load(string $path): array
    {
        $this->authorise();
        $url = $this->options['webURL'].'/_api/$batch';
        $retries = 3;
        for ($i = 0; $i < $retries; ++$i) {
            $request = new Request($url, 'POST');
            $request->setHeader('Accept', 'application/json; OData=verbose');
            $request->setHeader('X-RequestDigest', $this->_getFormDigest());
            $request->setHeader('X-RequestDigest', $this->_getFormDigest());
            $request->enableMultipart('multipart/mixed', 'batch_'.guid());
            $headers = ['Content-Transfer-Encoding' => 'binary'];
            $request->addMultipart('GET '.$this->_folder($path, 'folders')
                ." HTTP/1.1\nAccept: application/json; OData=verbose\n", 'application/http', $headers);
            $request->addMultipart('GET '.$this->_folder($path, 'files')
                ." HTTP/1.1\nAccept: application/json; OData=verbose\n", 'application/http', $headers);
            $response = $this->send($request);
            if (200 !== $response->status) {
                $this->requestFormDigest = null;
                if (401 === $response->status) {
                    $this->deleteCookies();
                }

                continue;
            }
            $responses = $response->body();
            if (2 !== count($responses)) {
                throw new Exception\SharePointError('Batch request error.  Requested 2 responses, got '.count($responses), $response);
            }
            array_walk($responses, function (&$value) {
                $response = new Response();
                $response->read($value['body']);
                $value = $response;
            });
            $folders = ake($responses[0]->body(), 'd.results', [], true);
            $files = ake($responses[1]->body(), 'd.results', [], true);
            $sort = function ($a, $b) {
                if ($a->Name === $b->Name) {
                    return 0;
                }

                return ($a->Name < $b->Name) ? -1 : 1;
            };
            usort($folders, $sort);
            usort($files, $sort);
            $items = [];
            foreach (array_merge($folders, $files) as $item) {
                $items[$item->Name] = $item;
            }

            return $items;
        }

        throw new Exception\SharePointError('Unable to load folder info after '.$retries.' retried.  Giving up!');
    }
}
