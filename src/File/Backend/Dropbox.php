<?php

declare(strict_types=1);

namespace Hazaar\File\Backend;

use Hazaar\Cache\Adapter;
use Hazaar\File\Backend\Exception\DropboxError;
use Hazaar\File\Image;
use Hazaar\File\Manager;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;

class Dropbox extends Client implements Interface\Backend, Interface\Driver
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
     * @var array<mixed>
     */
    private array $cursor;

    /**
     * @param array<mixed> $options
     */
    public function __construct(array $options, Manager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
        $this->options = array_merge_recursive([
            'oauth2_method' => 'POST',
            'oauth_version' => '2.0',
            'file_limit' => 1000,
            'cache_backend' => 'file',
            'oauth2' => ['access_token' => null],
        ], $options);
        if (!(isset($this->options['app_key'], $this->options['app_secret']))) {
            throw new DropboxError('Dropbox filesystem backend requires both app_key and app_secret.');
        }
        $this->cache = new Adapter($this->options['cache_backend'], ['use_pragma' => false, 'namespace' => 'dropbox_'.$this->options['app_key']]);
        if ($oauth2 = $this->cache->get('oauth2_data')) {
            $this->options['oauth2'] = $oauth2;
        }
        if (($cursor = $this->cache->get('cursor')) !== false) {
            $this->cursor = $cursor;
        }
        if (($meta = $this->cache->get('meta')) !== false) {
            $this->meta = $meta;
        }
    }

    public function __destruct()
    {
        $this->cache->set('meta', $this->meta);
        $this->cache->set('cursor', $this->cursor);
    }

    public static function label(): string
    {
        return 'Dropbox';
    }

    public function authorise(?string $redirect_uri = null): bool
    {
        if (($code = ake($_REQUEST, 'code')) && ($state = ake($_REQUEST, 'state'))) {
            if ($state != $this->cache->pull('oauth2_state')) {
                throw new \Exception('Bad state!');
            }
            $request = new Request('https://api.dropbox.com/1/oauth2/token', $this->options['oauth2_method']);
            $request->populate([
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $this->options['app_key'],
                'client_secret' => $this->options['app_secret'],
                'redirect_uri' => $redirect_uri,
            ]);
            $response = $this->send($request);
            if (200 !== $response->status) {
                return false;
            }
            if ($auth = json_decode($response->body, true)) {
                $this->cache->set('oauth2_data', $auth);

                return true;
            }
        }

        return $this->authorised();
    }

    public function authorised(): bool
    {
        return isset($this->options['oauth2']) && null !== $this->options['oauth2']['access_token'];
    }

    public function buildAuthURL(?string $redirect_uri = null): string
    {
        if (!$redirect_uri) {
            $redirect_uri = $_SERVER['REQUEST_URI'];
        }
        $state = md5(uniqid());
        $this->cache->set('oauth2_state', $state);
        $params = [
            'response_type=code',
            'client_id='.$this->options['app_key'],
            'redirect_uri='.$redirect_uri,
            'state='.$state,
        ];

        return 'https://www.dropbox.com/1/oauth2/authorize?'.implode('&', $params);
    }

    public function refresh(bool $reset = false): bool
    {
        if (!$this->authorised()) {
            return false;
        }
        $request = new Request('https://api.dropbox.com/1/delta', 'POST');
        if (!$reset && count($this->meta) && $this->cursor) {
            $request['cursor'] = $this->cursor;
        }
        $response = $this->sendRequest($request);
        $this->cursor = $response['cursor'];
        if (true === $response['reset']) {
            $this->meta = [
                '/' => [
                    'bytes' => 0,
                    'icon' => 'folder',
                    'path' => '/',
                    'is_dir' => true,
                    'thumb_exists' => false,
                    'root' => 'app_folder',
                    'modified' => 'Thu, 21 May 2015 06:06:57 +0000',
                    'size' => '0 bytes',
                ],
            ];
        }
        foreach ($response['entries'] as $entry) {
            list($path, $meta) = $entry->toArray();
            if ($meta) {
                $this->meta[$path] = $meta;
            } elseif (array_key_exists($path, $this->meta)) {
                unset($this->meta[$path]);
            }
        }
        ksort($this->meta);

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
        if (!$this->authorised()) {
            return false;
        }
        $path = strtolower('/'.ltrim($path, '/'));
        if (!($pathMeta = ake($this->meta, $path))) {
            return false;
        }
        if (!$pathMeta['is_dir']) {
            return false;
        }
        $list = [];
        foreach ($this->meta as $name => $meta) {
            if ('/' == $name || pathinfo($name, PATHINFO_DIRNAME) !== $path) {
                continue;
            }
            $list[] = basename($meta['path']);
        }

        return $list;
    }

    /**
     * @return array<mixed>|bool
     */
    public function info(string $path): array|bool
    {
        if (!$this->cursor) {
            $this->refresh();
        }
        if (!($meta = ake($this->meta, strtolower($path)))) {
            return false;
        }

        return $meta;
    }

    public function search(string $query, bool $include_deleted = false): false
    {
        throw new DropboxError('Search is not done yet!');
        /*
        $request = new Request('https://api.dropbox.com/1/search/auto/', 'POST');
        $request->query = $query;
        if ($this->options->has('file_limit')) {
            $request->file_limit = $this->options['file_limit'];
        }
        $request->include_deleted = $include_deleted;
        if (!($response = $this->sendRequest($request))) {
            return false;
        }

        return $response;
        */
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
        return $this->exists($path);
    }

    public function isWritable(string $path): bool
    {
        return $this->exists($path);
    }

    // TRUE if path is a directory
    public function isDir(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return $info['is_dir'];
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

        return !$info['is_dir'];
    }

    // Returns the file type
    public function filetype(string $path): false|string
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return $info['is_dir'] ? 'dir' : 'file';
    }

    // Returns the file modification time
    public function filectime(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return strtotime($info['created']);
    }

    // Returns the file modification time
    public function filemtime(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return strtotime($info['modified']);
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

        return $info['bytes'];
    }

    public function fileperms(string $path): false|int
    {
        return 0666;
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
        return $this->separator;
    }

    public function unlink(string $path): bool
    {
        $request = new Request('https://api.dropbox.com/1/fileops/delete', 'POST');
        $request['root'] = 'auto';
        $request['path'] = $path;
        $response = $this->sendRequest($request);
        if ($response['is_deleted']) {
            $key = strtolower($response['path']);
            if (array_key_exists($key, $this->meta)) {
                unset($this->meta[$key]);
            }
            $this->clearMeta($response['path']);

            return true;
        }

        return false;
    }

    public function mimeContentType(string $path): ?string
    {
        if (!($info = $this->info($path))) {
            return null;
        }

        return $info['is_dir'] ? 'dir' : $info['mime_type'];
    }

    public function md5Checksum(string $path): ?string
    {
        return md5($this->read($path));
    }

    /**
     * @param array<string,int|string> $params
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
            $response = $this->sendRequest($request, false);
            if (!is_string($response)) {
                return false;
            }
            $image = new Image($path, null, $this->manager);
            $image->setContents($response);
            $image->resize($width, $height, false, 'center', true, true, null, 0, 0);

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
        $response = $this->sendRequest($request);
        if (boolify($response['is_dir'])) {
            $this->meta[strtolower($response['path'])] = $response;

            return true;
        }

        return false;
    }

    public function rmdir(string $path, bool $recurse = false): bool
    {
        if (!$this->exists($path)) {
            return false;
        }
        if ($recurse) {
            $dir = $this->scandir($path, null, SCANDIR_SORT_ASCENDING, true);
            foreach ($dir as $file) {
                if ('.' == $file || '..' == $file) {
                    continue;
                }
                $fullpath = $path.DIRECTORY_SEPARATOR.$file;
                if ($this->isDir($fullpath)) {
                    $this->rmdir($fullpath, true);
                } else {
                    $this->unlink($fullpath);
                }
            }
        }

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
        $response = $this->sendRequest($request);
        $this->meta[strtolower($response['path'])] = $response;
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
        $response = $this->sendRequest($request);
        $this->meta[strtolower($response['path'])] = $response;
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
        $request = new Request('https://api-content.dropbox.com/1/files/auto'.$path, 'GET');
        if ($offset >= 0) {
            $range = 'bytes='.$offset.'-';
            if ($maxlen) {
                $range .= ($offset + ($maxlen - 1));
            }
            $request->setHeader('Range', $range);
        }

        return $this->sendRequest($request, false);
    }

    public function write(string $path, string $data, ?string $content_type = null, bool $overwrite = false): ?int
    {
        $request = new Request('https://api-content.dropbox.com/1/files_put/auto'.$path, 'POST');
        $request->setHeader('Content-Type', $content_type);
        if ($overwrite) {
            $request['overwrite'] = true;
        }
        $request['body'] = $data;
        $response = $this->sendRequest($request);
        $this->meta[strtolower($response['path'])] = $response;

        return strlen($data);
    }

    /**
     * @param array<string> $file
     */
    public function upload(string $path, array $file, bool $overwrite = true): bool
    {
        if (!(($srcFile = ake($file, 'tmp_name')) && $filetype = ake($file, 'type'))) {
            return false;
        }
        $fullPath = rtrim($path, '/').'/'.$file['name'];

        return $this->write($fullPath, file_get_contents($srcFile), $filetype, $overwrite = false) > 0;
    }

    /**
     * @return array<mixed>|false|string
     */
    public function getMeta(string $path, ?string $key = null): array|false|string
    {
        if ($meta = $this->cache->get($this->options['app_key'].'::'.strtolower($path))) {
            return ake($meta, $key);
        }

        return false;
    }

    /**
     * @param array<mixed> $values
     */
    public function setMeta(string $path, array $values): bool
    {
        if (!($meta = $this->cache->get($this->options['app_key'].'::'.strtolower($path)))) {
            $meta = [];
        }
        $meta = array_merge($meta, $values);
        $this->cache->set($this->options['app_key'].'::'.strtolower($path), $meta);

        return true;
    }

    /**
     * @param array<string,int|string> $params
     */
    public function previewURL(string $path, array $params = []): string
    {
        $width = (int) ake($params, 'width', ake($params, 'height', 64));
        if ($width >= 1024) {
            $size = 'w1024h768';
        } elseif ($width >= 640) {
            $size = 'w640h480';
        } elseif ($width >= 128) {
            $size = 'w128h128';
        } elseif ($width >= 64) {
            $size = 'w64h64';
        } else {
            $size = 'w32h32';
        }
        $params = [
            'authorization=Bearer '.$this->options['oauth2']['access_token'],
            'arg={"path":"'.$path.'","size":"'.$size.'"}',
        ];

        return 'https://content.dropboxapi.com/2/files/get_thumbnail?'.implode('&', $params);
    }

    public function directURL(string $path): false|string
    {
        if (!$this->exists($path)) {
            return false;
        }
        $info = $this->info($path);
        if ($info['is_dir']) {
            return false;
        }
        if (($media = ake($info, 'media')) && strtotime($media['expires']) > time()) {
            return $media['url'];
        }
        $request = new Request('https://api.dropbox.com/1/media/auto'.$path, 'POST');
        $response = $this->sendRequest($request);
        if ($response['url']) {
            $this->meta[strtolower($path)]['media'] = $response;

            return $response['url'];
        }

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

    public function find(?string $search = null, string $path = '/', bool $case_insensitive = false): array|false
    {
        return false;
    }

    public function fsck(bool $skip_root_reload = false): bool
    {
        return false;
    }

    /**
     * @return array<mixed>|string
     */
    private function sendRequest(Request $request, bool $isMeta = true): array|string
    {
        $request->setHeader('Authorization', 'Bearer '.$this->options['oauth2']['access_token']);
        $response = $this->send($request);
        if (200 != $response->status) {
            $meta = $response->body;
            if (isset($meta['error'])) {
                $err = $meta['error'];
            } else {
                $err = 'Unknown error!';
            }

            throw new DropboxError($err, $response->status);
        }
        if (true == $isMeta) {
            $meta = $response->body;
            if (isset($meta['error'])) {
                throw new DropboxError($meta['error']);
            }
        } else {
            $meta = $response->body;
        }

        return $meta;
    }

    private function clearMeta(string $path): bool
    {
        $this->cache->remove($this->options['app_key'].'::'.strtolower($path));

        return true;
    }
}
