<?php

declare(strict_types=1);

namespace Hazaar\File\Backend;

use Hazaar\Cache\Adapter;
use Hazaar\File\Interface\Backend as BackendInterface;
use Hazaar\File\Interface\Driver as DriverInterface;
use Hazaar\File\Manager;
use Hazaar\HTTP\Request;

class WebDAV extends \Hazaar\HTTP\WebDAV implements BackendInterface, DriverInterface
{
    public string $separator = '/';
    protected Manager $manager;

    /**
     * @var array<mixed>
     */
    private array $options;
    private Adapter $cache;

    /**
     * @var array<mixed>
     */
    private array $meta = [];

    /**
     * WebDAV constructor.
     *
     * @param array<mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'cache_backend' => 'file',
            'cache_meta' => false,
        ], $options);
        if (!isset($this->options['url'])) {
            throw new \Exception('WebDAV file browser backend requires a URL!');
        }
        if (isset($this->options['cookies'])) {
            $this->setCookie($this->options['cookies']);
        }
        $this->cache = new Adapter($this->options['cache_backend'], ['use_pragma' => false, 'namespace' => 'webdav_'.$this->options['url'].'_'.$this->options['username']]);
        if (($this->options['cache_meta'] ?? false)
            && ($meta = $this->cache->get('meta'))) {
            $this->meta = $meta;
        }
        parent::__construct($this->options);
    }

    public function __destruct()
    {
        if ($this->options['cache_meta'] ?? false) {
            $this->cache->set('meta', $this->meta);
        }
    }

    public static function label(): string
    {
        return 'WebDAV';
    }

    public function refresh(bool $reset = true): bool
    {
        return true;
    }

    // Metadata Operations
    public function scandir(
        string $path,
        ?string $regexFilter = null,
        int $sort = SCANDIR_SORT_ASCENDING,
        bool $showHidden = false,
        ?string $relativePath = null
    ): array|bool {
        $path = '/'.trim($path, '/');
        if (!array_key_exists($path, $this->meta) || false == $this->meta[$path]['scanned']) {
            $this->updateMeta($path);
        }
        if (!($pathMeta = $this->meta[$path] ?? null)) {
            return false;
        }
        if (!(is_array($pathMeta['resourcetype']) && array_key_exists('collection', $pathMeta['resourcetype']))) {
            return false;
        }
        $list = [];
        foreach ($this->meta as $name => $item) {
            if ('/' == $name || pathinfo($name, PATHINFO_DIRNAME) !== $path) {
                continue;
            }
            $list[] = basename($name);
        }

        return $list;
    }

    // Check if file/path exists
    public function exists(string $path): bool
    {
        if (false !== $this->info($path)) {
            return true;
        }

        return false;
    }

    public function realpath(string $path): ?string
    {
        return $path;
    }

    public function isReadable(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return true;
    }

    public function isWritable(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return true;
    }

    // TRUE if path is a directory
    public function isDir(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }
        if (array_key_exists('resourcetype', $info) && is_array($info['resourcetype']) && array_key_exists('collection', $info['resourcetype'])) {
            return true;
        }

        return false;
    }

    // TRUE if path is a symlink
    public function isLink(string $path): bool
    {
        return false;
    }

    // TRUE if path is a normal file
    public function isFile(string $path): bool
    {
        return !$this->isDir($path);
    }

    // Returns the file type
    public function filetype(string $path): false|string
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return is_array($info['resourcetype']) && array_key_exists('collection', $info['resourcetype']) ? 'dir' : 'file';
    }

    // Returns the file modification time
    public function filectime(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return strtotime($info['getcreated'] ?? '');
    }

    // Returns the file modification time
    public function filemtime(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return strtotime($info['getlastmodified'] ?? '');
    }

    public function touch(string $path): bool
    {
        return false;
    }

    // Returns the file modification time
    public function fileatime(string $path): false|int
    {
        return false;
    }

    public function filesize(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }
        if ($this->isDir($path)) {
            return (int) ($info['size'] ?? 0);
        }

        return (int) ($info['getcontentlength'] ?? 0);
    }

    public function fileperms(string $path): false|int
    {
        return false;
    }

    public function chmod(string $path, int $mode): bool
    {
        return true;
    }

    public function chown(string $path, string $user): bool
    {
        return true;
    }

    public function chgrp(string $path, string $group): bool
    {
        return true;
    }

    public function cwd(): string
    {
        return '/';
    }

    public function unlink(string $path): bool
    {
        $request = new Request($this->getAbsoluteUrl($path), 'DELETE');
        $response = $this->send($request);
        if ($response && (204 === $response->status || 200 === $response->status)) {
            // Optionally update meta cache
            unset($this->meta[$path]);

            return true;
        }

        return false;
    }

    public function mimeContentType(string $path): ?string
    {
        if (!($info = $this->info($path))) {
            return null;
        }

        return $info['getcontenttype'] ?? null;
    }

    public function md5Checksum(string $path): ?string
    {
        if (!($bytes = $this->read($path))) {
            return null;
        }

        return md5($bytes);
    }

    /**
     * Get the URL to a thumbnail of the file.
     *
     * @param array<string, int|string> $params
     */
    public function thumbnailURL(string $path, int $width = 100, int $height = 100, string $format = 'jpeg', array $params = []): false|string
    {
        return false;
    }

    // File Operations
    public function mkdir(string $path): bool
    {
        $request = new Request($this->getAbsoluteUrl($path), 'MKCOL');
        $response = $this->send($request);
        if ($response && (201 === $response->status || 200 === $response->status)) {
            // Optionally update meta cache
            $this->updateMeta(pathinfo($path, PATHINFO_DIRNAME));

            return true;
        }

        return false;
    }

    public function rmdir(string $path, bool $recurse = false): bool
    {
        return $this->unlink($path);
    }

    public function copy(string $src, string $dst, bool $overwrite = false): bool
    {
        if ($this->exists($dst) && !$overwrite) {
            return false;
        }
        $request = new Request($this->getAbsoluteUrl($src), 'COPY');
        $request->setHeader('Destination', $this->getAbsoluteUrl($dst)->path());
        $response = $this->send($request);
        if ($response && (201 === $response->status || 204 === $response->status)) {
            $this->updateMeta(pathinfo($dst, PATHINFO_DIRNAME));

            return true;
        }

        return false;
    }

    public function link(string $src, string $dst): bool
    {
        return false;
    }

    public function move(string $src, string $dst, bool $overwrite = false): bool
    {
        if ($this->exists($dst) && !$overwrite) {
            return false;
        }
        $request = new Request($this->getAbsoluteUrl($src), 'MOVE');
        $request->setHeader('Destination', $this->getAbsoluteUrl($dst)->path());
        if (!$overwrite) {
            $request->setHeader('Overwrite', 'F');
        }
        $response = $this->send($request);
        if ($response && in_array($response->status, [201, 204])) {
            unset($this->meta[$src]);
            $this->updateMeta(pathinfo($src, PATHINFO_DIRNAME));
            $this->updateMeta(pathinfo($dst, PATHINFO_DIRNAME));

            return true;
        }

        return false;
    }

    // Access operations
    public function read(string $path, int $offset = -1, ?int $maxlen = null): false|string
    {
        $response = $this->get($this->getAbsoluteUrl($path), 10, $offset, $maxlen);
        if (200 !== $response->status) {
            return false;
        }

        return $response->body;
    }

    public function write(string $path, string $data, ?string $contentType = null, bool $overwrite = false): ?int
    {
        $request = new Request($this->getAbsoluteUrl($path), 'PUT');
        $request->setHeader('Content-Type', $contentType ?? 'application/octet-stream');
        if ($overwrite) {
            $request['overwrite'] = true;
        }
        $request->setBody($data);
        $response = $this->send($request);
        if (200 !== $response->status && 201 !== $response->status) {
            return null;
        }
        $info = $this->info($path, true);
        if (!$info) {
            return null;
        }

        return intval($info['getcontentlength'] ?? 0);
    }

    /**
     * Upload a file that was uploaded with a POST.
     *
     * @param array<string, int|string> $file
     */
    public function upload(string $path, array $file, bool $overwrite = true): bool
    {
        if (!(($srcFile = $file['tmp_name'] ?? null) && $filetype = $file['type'] ?? null)) {
            return false;
        }
        $fullPath = rtrim($path, '/').'/'.$file['name'];

        return $this->write($fullPath, file_get_contents($srcFile), $filetype, $overwrite = false) > 0;
    }

    public function getMeta(string $path, ?string $key = null): array|false|string
    {
        if (!($meta = $this->cache->get($this->options['app_key'].'::'.strtolower($path)))) {
            return false;
        }
        if (null !== $key) {
            return $meta[$key] ?? false;
        }

        return $meta;
    }

    /**
     * @param array<string,int|string> $values
     */
    public function setMeta(string $path, array $values): bool
    {
        if (!($meta = $this->cache->get($this->options['app_key'].'::'.strtolower($path)))) {
            $meta = [];
        }
        foreach ($values as $key => $value) {
            $meta[$key] = $value;
        }
        $this->cache->set($this->options['app_key'].'::'.strtolower($path), $meta);

        return true;
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
        return false;
    }

    public function authorised(): bool
    {
        return true;
    }

    public function authorise(?string $redirectUri = null): bool
    {
        return true;
    }

    public function authoriseWithCode(string $code, ?string $redirectUri = null, string $grantType = 'authorization_code'): bool
    {
        return true;
    }

    public function buildAuthURL(?string $callbackUrl = null): ?string
    {
        return null;
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

    public function find(?string $search = null, string $path = '/', bool $caseInsensitive = false): array|false
    {
        return false;
    }

    public function fsck(bool $skipRoot_reload = false): bool
    {
        return false;
    }

    /**
     * @return array<mixed>
     */
    private function info(string $path, bool $forceUpdate = false): array|false
    {
        $path = '/'.trim($path, '/');
        if (true !== $forceUpdate && isset($this->meta[$path])) {
            return $this->meta[$path];
        }
        if (!$this->updateMeta($path)) {
            return false;
        }

        return $this->meta[$path] ?? false;
    }

    private function updateMeta(string $path): bool
    {
        try {
            if (!($meta = $this->propfind($path))) {
                return false;
            }
            $meta = array_merge($this->meta, $meta);
            foreach ($meta as $name => $info) {
                $info['scanned'] = $name == $path;
                $this->meta[$name] = $info;
            }
        } catch (\Exception $e) {
            // Log the exception or handle it as needed
            return false;
        }

        return true;
    }
}
