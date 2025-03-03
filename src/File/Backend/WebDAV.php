<?php

declare(strict_types=1);

namespace Hazaar\File\Backend;

use Hazaar\Cache\Adapter;
use Hazaar\File\Image;
use Hazaar\File\Interface\Backend as BackendInterface;
use Hazaar\File\Interface\Driver as DriverInterface;
use Hazaar\File\Manager;
use Hazaar\HTTP\Request;
use Hazaar\HTTP\Response;

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
    public function __construct(array $options, Manager $manager)
    {
        $this->manager = $manager;
        $this->options = array_merge([
            'cache_backend' => 'file',
            'cache_meta' => true,
        ], $options);
        if (!isset($this->options['baseuri'])) {
            throw new \Exception('WebDAV file browser backend requires a URL!');
        }
        if (isset($this->options['cookies'])) {
            $this->setCookie($this->options['cookies']);
        }
        $this->cache = new Adapter($this->options['cache_backend'], ['use_pragma' => false, 'namespace' => 'webdav_'.$this->options['baseuri'].'_'.$this->options['username']]);
        if ($this->options['cache_meta'] ?? false) {
            if (($meta = $this->cache->get('meta')) !== false) {
                $this->meta = $meta;
            }
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
        ?string $regex_filter = null,
        int $sort = SCANDIR_SORT_ASCENDING,
        bool $show_hidden = false,
        ?string $relative_path = null
    ): array|bool {
        $path = '/'.trim($path, '/');
        if (!array_key_exists($path, $this->meta) || false == $this->meta[$path]['scanned']) {
            $this->updateMeta($path);
        }
        if (!($pathMeta = ake($this->meta, $path))) {
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
        if (($info = $this->info($path)) !== false) {
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

        return in_array('R', str_split(ake($info, 'permissions')));
    }

    public function isWritable(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return in_array('W', str_split(ake($info, 'permissions')));
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

        return strtotime(ake($info, 'getcreated'));
    }

    // Returns the file modification time
    public function filemtime(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return strtotime(ake($info, 'getlastmodified'));
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
            return (int) ake($info, 'size');
        }

        return (int) ake($info, 'getcontentlength');
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
        return false;
    }

    public function mimeContentType(string $path): ?string
    {
        if (!($info = $this->info($path))) {
            return null;
        }

        return ake($info, 'getcontenttype');
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
        if (!($info = $this->info($path))) {
            return false;
        }
        if ($info['thumb_exists']) {
            $size = 'l';
            if ($width < 32 && $height < 32) {
                $size = 'xs';
            } elseif ($width < 64 && $height < 64) {
                $size = 's';
            } elseif ($width < 128 && $height < 128) {
                $size = 'm';
            } elseif ($width < 640 && $height < 480) {
                $size = 'l';
            } elseif ($width < 1024 && $height < 768) {
                $size = 'xl';
            }
            $request = new Request('https://api-content.dropbox.com/1/thumbnails/auto'.$path, 'GET');
            $request['format'] = $format;
            $request['size'] = $size;
            $response = $this->send($request, 0);
            $image = new Image($path, null, $this->manager);
            $image->setContents($response->body);
            $image->resize($width, $height, true, 'center', true, true, null, 0, 0);

            return $image->getContents();
        }

        return false;
    }

    // File Operations
    public function mkdir(string $path): bool
    {
        $request = new Request('https://api.dropbox.com/1/fileops/create_folder', 'POST');
        $request['root'] = 'auto';
        $request['path'] = $path;
        $response = $this->send($request);
        if ($response instanceof Response && boolify($response['is_dir'])) {
            $this->meta[strtolower($response['path'])] = $response->body;

            return true;
        }

        return false;
    }

    public function rmdir(string $path, bool $recurse = false): bool
    {
        return $this->unlink($path);
    }

    public function copy(string $src, string $dst, bool $recursive = false): bool
    {
        if ($this->isFile($dst)) {
            return false;
        }
        $dst = rtrim($dst, '/').'/'.basename($src);
        if ($this->exists($dst)) {
            return false;
        }
        $request = new Request('https://api.dropbox.com/1/fileops/copy', 'POST');
        $request['root'] = 'auto';
        $request['from_path'] = $src;
        $request['to_path'] = $dst;
        $response = $this->send($request);
        $this->meta[strtolower($response['path'])] = $response->body;
        $key = $this->options['app_key'].'::'.strtolower($src);
        if ($meta = $this->cache->get($key)) {
            $this->cache->set($this->options['app_key'].'::'.strtolower($response['path']), $meta);
        }

        return true;
    }

    public function link(string $src, string $dst): bool
    {
        return false;
    }

    public function move(string $src, string $dst): bool
    {
        if ($this->isFile($dst)) {
            return false;
        }
        $dst = rtrim($dst, '/').'/'.basename($src);
        if ($this->exists($dst)) {
            return false;
        }
        $request = new Request('https://api.dropbox.com/1/fileops/move', 'POST');
        $request['root'] = 'auto';
        $request['from_path'] = $src;
        $request['to_path'] = $dst;
        $response = $this->send($request);
        $this->meta[strtolower($response['path'])] = $response->body;
        $key = $this->options['app_key'].'::'.strtolower($src);
        if ($meta = $this->cache->get($key)) {
            $this->cache->set($this->options['app_key'].'::'.strtolower($response['path']), $meta);
            $this->cache->remove($key);
        }

        return true;
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

    public function write(string $path, string $data, ?string $content_type = null, bool $overwrite = false): ?int
    {
        $request = new Request('https://api-content.dropbox.com/1/files_put/auto'.$path, 'POST');
        $request->setHeader('Content-Type', $content_type);
        if ($overwrite) {
            $request['overwrite'] = true;
        }
        $request['body'] = $data;
        $response = $this->send($request);
        $this->meta[strtolower($response['path'])] = $response->body;

        return strlen($data);
    }

    /**
     * Upload a file that was uploaded with a POST.
     *
     * @param array<string, int|string> $file
     */
    public function upload(string $path, array $file, bool $overwrite = true): bool
    {
        if (!(($srcFile = ake($file, 'tmp_name')) && $filetype = ake($file, 'type'))) {
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
            return ake($meta, $key);
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

    public function authorise(?string $redirect_uri = null): bool
    {
        return true;
    }

    public function buildAuthURL(?string $callback_url = null): ?string
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

    public function find(?string $search = null, string $path = '/', bool $case_insensitive = false): array|false
    {
        return false;
    }

    public function fsck(bool $skip_root_reload = false): bool
    {
        return false;
    }

    /**
     * @return array<mixed>
     */
    private function info(string $path): array|false
    {
        $path = '/'.trim($path, '/');
        if ($meta = ake($this->meta, $path)) {
            return $meta;
        }
        if (!$this->updateMeta($path)) {
            return false;
        }

        return ake($this->meta, $path, false);
    }

    private function updateMeta(string $path): bool
    {
        if (!($meta = $this->propfind($path))) {
            return false;
        }
        $meta = array_merge($this->meta, $meta);
        foreach ($meta as $name => $info) {
            $name = '/'.trim($name, '/');
            $info['scanned'] = ($name == $path);
            $this->meta[$name] = $info;
        }

        return true;
    }
}
