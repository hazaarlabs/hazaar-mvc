<?php

declare(strict_types=1);

namespace Hazaar\File\Backend;

use Hazaar\Cache\Adapter;
use Hazaar\File\Backend\Exception\DropboxError;
use Hazaar\File\Interface\Backend as BackendInterface;
use Hazaar\File\Interface\Driver as DriverInterface;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;

class Dropbox extends Client implements BackendInterface, DriverInterface
{
    public string $separator = '/';
    private string $apiURL = 'https://api.dropbox.com/2';
    private string $apiContentURL = 'https://content.dropboxapi.com/2';

    /**
     * @var array<mixed>
     */
    private array $options;
    private Adapter $cache;

    /**
     * @var array<string,\stdClass>
     */
    private array $meta = [];

    /**
     * @var array<string,string>
     */
    private array $cursors;

    public function __construct(array $options = [])
    {
        parent::__construct();
        $this->options = array_replace_recursive([
            'file_limit' => 1000,
            'cache' => [
                'backend' => 'file',
            ],
            'oauth2' => null,
        ], $options);
        if (!(isset($this->options['app_key'], $this->options['app_secret']))) {
            throw new DropboxError('Dropbox filesystem backend requires both app_key and app_secret.');
        }
        if (!isset($this->options['cache']['backend'])) {
            throw new DropboxError('Dropbox filesystem backend requires cache backend to be set.');
        }
        if (!isset($this->options['cache']['namespace'])) {
            $this->options['cache']['namespace'] = 'dropbox:'.$this->options['app_key'];
        }
        $this->options['cache']['options']['use_pragma'] = false;
        $this->cache = new Adapter(
            $this->options['cache']['backend'],
            $this->options['cache']['options'],
            $this->options['cache']['namespace']
        );
        $this->reload();
    }

    public function __destruct()
    {
        if (isset($this->cursors)) {
            $this->cache->set('cursors', $this->cursors);
            $this->cache->set('meta', $this->meta);
        } else {
            // If cursors are not set, we can clear it from cache
            $this->cache->remove('cursors');
            $this->cache->remove('meta');
        }
    }

    public static function label(): string
    {
        return 'Dropbox';
    }

    public function reload(): void
    {
        if ($oauth2 = $this->cache->get('oauth2_data')) {
            $this->options['oauth2'] = $oauth2;
        }
        if ($cursors = $this->cache->get('cursors')) {
            $this->cursors = $cursors;
            if ($meta = $this->cache->get('meta')) {
                $this->meta = $meta;
            }
        }
    }

    public function reset(): bool
    {
        $this->cursors = [];
        $this->meta = [];
        $this->options['oauth2'] = null;
        $this->cache->remove('cursors');
        $this->cache->remove('meta');
        $this->cache->remove('oauth2_data');

        return true;
    }

    public function authorise(?string $redirectUri = null): bool
    {
        if (($code = $_REQUEST['code'] ?? null) && ($state = $_REQUEST['state'] ?? null)) {
            if ($state != $this->cache->pull('oauth2_state')) {
                return false;
            }

            return $this->authoriseWithCode($code);
        }

        return $this->authorised();
    }

    public function authoriseWithCode(
        string $code,
        ?string $redirectUri = null,
        string $grantType = 'authorization_code'
    ): bool {
        $request = new Request('https://api.dropboxapi.com/oauth2/token', 'POST');
        if ('refresh_token' === $grantType) {
            $request['refresh_token'] = $code;
        } else {
            $request['code'] = $code;
        }
        $request['grant_type'] = $grantType;
        $request['client_id'] = $this->options['app_key'];
        $request['client_secret'] = $this->options['app_secret'];
        $response = $this->send($request);
        if ($redirectUri) {
            $request['redirect_uri'] = $redirectUri;
        }
        if (200 !== $response->status) {
            return false;
        }
        if ($auth = $response->body()) {
            if (isset($auth->expires_in)) {
                $auth->expires = time() + ($auth->expires_in - 1); // Pull a second off to avoid expiry issues
            }
            $this->options['oauth2'] = array_merge($this->options['oauth2'] ?? [], (array) $auth);
            $this->cache->set('oauth2_data', $this->options['oauth2']);

            return true;
        }

        return false;
    }

    public function authorised(): bool
    {
        if (!(isset($this->options['oauth2']) && null !== $this->options['oauth2']['access_token'])) {
            return false;
        }
        // Check if the access token is still valid
        if (isset($this->options['oauth2']['expires']) && $this->options['oauth2']['expires'] < time()) {
            if (!isset($this->options['oauth2']['refresh_token'])) {
                return false; // Access\Refresh token has expired
            }

            return $this->authoriseWithCode(
                $this->options['oauth2']['refresh_token'],
                null,
                'refresh_token'
            );
        }

        return true;
    }

    public function buildAuthURL(?string $redirectUri = null): string
    {
        $state = md5(uniqid());
        $this->cache->set('oauth2_state', $state, 300);
        $params = [
            'response_type=code',
            'client_id='.$this->options['app_key'],
            'state='.$state,
            'token_access_type=offline',
        ];
        if ($redirectUri) {
            $params[] = 'redirect_uri='.urlencode($redirectUri);
        }

        return 'https://www.dropbox.com/oauth2/authorize?'.implode('&', $params);
    }

    public function refresh(bool $reset = false): bool
    {
        if (true === $reset) {
            unset($this->cursors);
        } else {
            foreach ($this->cursors as $path => $cursor) {
                $request = new Request(
                    "{$this->apiURL}/files/list_folder/continue",
                    'POST',
                    'application/json'
                );
                $request['cursor'] = $cursor;
                $response = $this->sendRequest($request);
                if (isset($response->entries)) {
                    foreach ($response->entries as $entry) {
                        $this->meta[$entry->path_lower] = $entry;
                    }
                }
            }
        }

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
        if (!$this->authorised()) {
            return false;
        }
        $path = strtolower('/'.ltrim($path, '/'));
        if (!($pathMeta = $this->meta[$path] ?? null)) {
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

    public function info(string $path): false|\stdClass
    {
        if (!$this->authorised()) {
            return false;
        }
        if (isset($this->meta[$path])) {
            return $this->meta[$path];
        }
        $request = new Request(
            "{$this->apiURL}/files/list_folder",
            'POST',
            'application/json'
        );
        $folderPath = dirname($path);
        // If the path is root, we need to set it to empty string as required by Dropbox API.
        if ('/' === $folderPath) {
            $folderPath = '';
        }
        if (isset($this->cursors[$folderPath])) {
            $request->appendURL('/continue');
            $request['cursor'] = $this->cursors[$folderPath];
        } else {
            $request['path'] = $folderPath; // Dropbox API expects empty string for root
        }
        $response = $this->sendRequest($request);
        if (!$response) {
            return false;
        }
        $this->cursors[$folderPath] = $response->cursor;
        if (!isset($this->meta[$path])) {
            $this->meta['/'] = (object) [
                '.tag' => 'folder',
                'name' => 'Root Folder',
                'path_lower' => '/',
                'path_display' => '/',
            ];
        }
        foreach ($response->entries as $entry) {
            $this->meta[$entry->path_lower] = $entry;

            // TODO: This is the old code to remove meta from cache when a file is deleted.
            // if ($meta) {
            //     $this->meta[$entry->path_lower] = $meta;
            // } elseif (array_key_exists($path, $this->meta)) {
            //     unset($this->meta[$path]);
            // }
        }
        ksort($this->meta);

        return $this->meta[$path] ?? false;
    }

    public function search(string $query, bool $includeDeleted = false): false
    {
        throw new DropboxError('Search is not done yet!');
        /*
        $request = new Request("{$this->apiURL}/files/search', 'POST');
        $request->query = $query;
        if ($this->options->has('file_limit')) {
            $request->file_limit = $this->options['file_limit'];
        }
        $request->include_deleted = $includeDeleted;
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
            return 'deleted' !== ($info->{'.tag'} ?? null);
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

        return 'folder' === ($info->{'.tag'} ?? null);
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

        return 'file' === $info->{'.tag'};
    }

    // Returns the file type
    public function filetype(string $path): false|string
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return $info->{'.tag'};
    }

    // Returns the file modification time
    public function filectime(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return strtotime($info->client_modified);
    }

    // Returns the file modification time
    public function filemtime(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return strtotime($info->server_modified);
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

        return $info->size ?? 0;
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
        $request = new Request(
            "{$this->apiURL}/files/delete_v2",
            'POST',
            'application/json'
        );
        $request['path'] = $path;
        $response = $this->sendRequest($request);
        if (!isset($response->metadata)) {
            return false;
        }
        if (array_key_exists($response->metadata->path_lower, $this->meta)) {
            unset($this->meta[$response->metadata->path_lower]);
        }

        return true;
    }

    public function mimeContentType(string $path): ?string
    {
        if (!($info = $this->info($path))) {
            return null;
        }

        return 'folder' === $info->{'.tag'} ? 'dir' : null;
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

        return "{$this->apiContentURL}/files/get_thumbnail_v2"
            .'?path='.urlencode($path)
            .'&format='.$format
            .'&size=w'.$width.'h'.$height
            .'&mode=fit'
            .'&thumbnail_size=w'.$width.'h'.$height;
    }

    // File Operations
    public function mkdir(string $path): bool
    {
        $request = new Request(
            "{$this->apiURL}/files/create_folder_v2",
            'POST',
            'application/json'
        );
        $request['path'] = $path;
        $response = $this->sendRequest($request);
        if (!(isset($response->metadata) && $response->metadata->path_lower)) {
            return false;
        }
        $response->metadata->{'.tag'} = 'folder';
        $this->meta[$response->metadata->path_lower] = $response->metadata;

        return true;
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

    public function copy(string $src, string $dst, bool $overwrite = false): bool
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
        $request = new Request(
            "{$this->apiContentURL}/files/download",
            'GET',
            'application/octet-stream'
        );
        $args = [
            'path' => $path,
        ];
        if ($offset >= 0) {
            $range = 'bytes='.$offset.'-';
            if ($maxlen) {
                $range .= ($offset + ($maxlen - 1));
            }
            $request->setHeader('Range', $range);
        }
        $request->setHeader('Dropbox-API-Arg', json_encode($args));

        return $this->sendRequest($request, false);
    }

    public function write(string $path, string $data, ?string $contentType = null, bool $overwrite = false): ?int
    {
        $request = new Request(
            "{$this->apiContentURL}/files/upload",
            'POST',
            'application/octet-stream'
        );
        $args = [
            'path' => $path,
            'mode' => $overwrite ? 'overwrite' : 'add',
        ];
        $request->setHeader('Dropbox-API-Arg', json_encode($args));
        $request->setBody($data);
        $response = $this->sendRequest($request);
        if (!$response || !isset($response->id)) {
            return null;
        }
        $this->meta[$response->path_lower] = $response;

        return $response->size;
    }

    /**
     * @param array<string> $file
     */
    public function upload(string $path, array $file, bool $overwrite = true): bool
    {
        if (!(($srcFile = $file['tmp_name'] ?? null) && $filetype = $file['type'] ?? null)) {
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
            return null !== $key ? ($meta[$key] ?? null) : $meta;
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
        $width = (int) ($params['width'] ?? $params['height'] ?? 64);
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
        if ('file' !== $info->{'.tag'}) {
            return false;
        }
        if (($media = $info->media ?? null) && strtotime($media->expires) > time()) {
            return $media['url'];
        }
        $request = new Request('https://api.dropbox.com/1/media/auto'.$path, 'POST');
        $response = $this->sendRequest($request);
        if ($response->url) {
            $this->meta[strtolower($path)]['media'] = $response;

            return $response->url;
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

    public function find(?string $search = null, string $path = '/', bool $caseInsensitive = false): array|false
    {
        return false;
    }

    public function fsck(bool $skipRoot_reload = false): bool
    {
        return false;
    }

    private function sendRequest(Request $request, bool $isMeta = true): mixed
    {
        $request->setHeader('Authorization', 'Bearer '.$this->options['oauth2']['access_token']);
        $response = $this->send($request);
        // If the response status is 409, it means the request was made to a path that does not exist or is not accessible.
        // This is a common scenario when the path is not found or the user does not have permission to access it.
        // It is not an error condition, so we do not throw an exception.
        if (409 === $response->status) {
            return null;
        }
        if (200 != $response->status) {
            $err = $response->body;

            throw new DropboxError($err, $response->status);
        }
        if (true !== $isMeta) {
            return $response->body();
        }
        $meta = $response->body();
        if (isset($meta->error)) {
            throw new DropboxError($meta->error);
        }

        return $meta;
    }
}
