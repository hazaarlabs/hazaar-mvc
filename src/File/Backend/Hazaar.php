<?php

declare(strict_types=1);

namespace Hazaar\File\Backend;

use Hazaar\File\Interface\Backend as BackendInterface;
use Hazaar\File\Interface\Driver as DriverInterface;
use Hazaar\File\Manager;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;
use Hazaar\HTTP\URL;

class Hazaar implements BackendInterface, DriverInterface
{
    public string $separator = '/';
    protected Manager $manager;

    /**
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * @var array<string, mixed>
     */
    private array $pathCache = [];

    /**
     * @var array<string, mixed>
     */
    private array $meta = [];

    private Client $client;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options, Manager $manager)
    {
        $this->manager = $manager;
        $this->options = array_merge([
            'url' => null,
        ], $options);
        $this->client = new Client();
    }

    public static function label(): string
    {
        return 'Hazaar VFS';
    }

    public function refresh(bool $reset = false): bool
    {
        $this->pathCache = [];

        return true;
    }

    public function scandir(
        string $path,
        ?string $regex_filter = null,
        int $sort = SCANDIR_SORT_ASCENDING,
        bool $show_hidden = false,
        ?string $relative_path = null
    ): array|bool {
        if (!$this->pathCache) {
            $this->pathCache = [
                '/' => [
                    'id' => base64url_encode('/'),
                    'kind' => 'dir',
                    'name' => 'ROOT',
                    'path' => '/',
                    'link' => $this->options['url'],
                    'parent' => null,
                    'mime' => 'dir',
                    'read' => true,
                    'write' => false,
                    'dirs' => 0,
                    'files' => [],
                ],
            ];
            if ($paths = $this->request('tree')) {
                foreach ($paths as $p) {
                    list($source, $base) = explode(':', base64url_decode($p['parent']), 2);
                    if (!$base) {
                        $p['name'] = $source;
                        $p['parent'] = $this->pathCache['/']['id'];
                    }
                    $source_path = '/'.$source.$p['path'];
                    if ('/' == $p['path']) {
                        $source_path = rtrim($source_path, '/');
                        ++$this->pathCache['/']['dirs'];
                    }
                    $this->pathCache[$source_path] = $p;
                }
            }
        }
        if (!array_key_exists($path, $this->pathCache)) {
            return false;
        }
        if (!array_key_exists('files', $this->pathCache[$path])) {
            $this->pathCache[$path]['files'] = [];
            if ($info = $this->request('open', ['target' => $this->pathCache[$path]['id'], 'with_meta' => true])) {
                foreach ($info['files'] as $file) {
                    $this->pathCache[$path]['files'][$file['name']] = $file;
                }
            }
        }
        $items = [];
        foreach ($this->pathCache as $d) {
            if ($d['parent'] === $this->pathCache[$path]['id']) {
                $items[] = $d['name'];
            }
        }
        foreach ($this->pathCache[$path]['files'] as $file) {
            $items[] = $file['name'];
        }

        return $items;
    }

    // Check if file/path exists
    public function exists(string $path): bool
    {
        return is_array($this->info($path));
    }

    public function realpath(string $path): string
    {
        return $path;
    }

    public function isReadable(string $path): bool
    {
        if ($info = $this->info($path)) {
            return ake($info, 'read', false);
        }

        return false;
    }

    public function isWritable(string $path): bool
    {
        if ($info = $this->info($path)) {
            return ake($info, 'write', false);
        }

        return false;
    }

    // TRUE if path is a directory
    public function isDir(string $path): bool
    {
        if ($info = $this->info($path)) {
            return 'dir' == ake($info, 'kind');
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
        if ($info = $this->info($path)) {
            return 'file' == ake($info, 'kind', 'file');
        }

        return false;
    }

    // Returns the file type
    public function filetype(string $path): false|string
    {
        if ($info = $this->info($path)) {
            return ake($info, 'kind', 'file');
        }

        return false;
    }

    // Returns the file modification time
    public function filectime(string $path): false|int
    {
        if ($info = $this->info($path)) {
            return ake($info, 'created');
        }

        return false;
    }

    // Returns the file modification time
    public function filemtime(string $path): false|int
    {
        if ($info = $this->info($path)) {
            return ake($info, 'modified');
        }

        return false;
    }

    // Returns the file modification time
    public function fileatime(string $path): false|int
    {
        return false;
    }

    public function filesize(string $path): false|int
    {
        if ($info = $this->info($path)) {
            return ake($info, 'size', 0);
        }

        return false;
    }

    public function unlink(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }
        $result = $this->request('unlink', ['target' => $info['id']]);
        if (is_array($result)) {
            if ('dir' == $info['kind']) {
                dump('delete dir');
            } else {
                $dir = dirname($path);
                if (array_key_exists($dir, $this->pathCache)) {
                    unset($this->pathCache[$dir]['files'][$info['name']]);
                }
            }

            return true;
        }

        return false;
    }

    public function mimeContentType(string $path): ?string
    {
        if ($info = $this->info($path)) {
            return ake($info, 'mime', false);
        }

        return null;
    }

    public function md5Checksum(string $path): string
    {
        return md5($this->read($path));
    }

    /**
     * @param array<string, int|string> $params
     */
    public function thumbnail(string $path, array $params = []): false|string
    {
        if ($link = ake($this->info($path), 'link')) {
            $uri = new URL($link);
            $uri->setParams($params);

            return file_get_contents((string) $uri);
        }

        return false;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function &info(string $path): array|false
    {
        $is_dir = $this->scandir($path);
        if (false === $is_dir) {
            $dir = $this->info(dirname($path));
            if ($info = ake(ake($dir, 'files'), basename($path))) {
                return $info;
            }
        } else {
            if ($info = ake($this->pathCache, $path)) {
                return $info;
            }
        }

        return false;
    }

    public function mkdir(string $path): bool
    {
        $parent = $this->info(dirname($path));
        $result = $this->request('mkdir', ['parent' => $parent['id'], 'name' => basename($path)]);
        if ($tree = ake($result, 'tree')) {
            foreach ($tree as $d) {
                $source = explode(':', base64url_decode($d['id']), 2)[0];
                $source_path = '/'.$source.$d['path'];
                $this->pathCache[$source_path] = $d;
            }

            return true;
        }

        return false;
    }

    public function rmdir(string $path, bool $recurse = false): bool
    {
        $info = ake($this->pathCache, $path);
        if ('dir' == !$info['kind']) {
            return false;
        }
        $result = $this->request('rmdir', ['target' => $info['id']]);
        if (ake($result, 'ok', false)) {
            unset($this->pathCache[$path]);

            return true;
        }

        return false;
    }

    public function read(string $path, int $offset = -1, ?int $maxlen = null): false|string
    {
        if (!($info = $this->info($path))) {
            return false;
        }
        dump($info);
        dump(__METHOD__);

        return false;
    }

    public function write(string $path, string $bytes, ?string $content_type = null, bool $overwrite = false): ?int
    {
        $parent = $this->info(dirname($path));
        if (!$parent) {
            return null;
        }
        $content = [$bytes, [
            'Content-Disposition' => 'form-data; name="file"; filename="'.basename($path).'"',
            'Content-Type' => $content_type,
        ]];
        $info = $this->request('upload', ['parent' => $parent['id'], 'overwrite' => $overwrite], [$content]);
        if ($info) {
            if ($fileInfo = ake($info, 'file')) {
                if ($meta = ake($this->meta, $path)) {
                    $this->request('set_meta', ['target' => $fileInfo['id'], 'values' => $meta]);
                }
                $source_path = '/'.explode(':', base64url_decode($fileInfo['parent']), 2)[0];
                if (array_key_exists($source_path, $this->pathCache)) {
                    $this->pathCache[$source_path]['files'][$fileInfo['name']] = $fileInfo;
                }

                return strlen($bytes);
            }
        }

        return null;
    }

    /**
     * @param array<string> $file
     */
    public function upload(string $path, array $file, bool $overwrite = false): bool
    {
        dump(__METHOD__);

        return false;
    }

    public function copy(string $src, string $dst, bool $recursive = false): bool
    {
        dump(__METHOD__);

        return false;
    }

    public function move(string $src, string $dst): bool
    {
        dump(__METHOD__);

        return false;
    }

    public function link(string $src, string $dst): bool
    {
        dump(__METHOD__);

        return false;
    }

    public function fileperms(string $path): false|int
    {
        dump(__METHOD__);
        if (!($info = $this->info($path))) {
            return false;
        }

        return ake($info, 'mode');
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
        return '/';
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setMeta(string $path, array $values): bool
    {
        if ($info = &$this->info($path)) {
            $info['meta'] = array_merge(ake($info, 'meta'), $values);

            return $this->request('set_meta', ['target' => $info['_id'], 'values' => $values]);
        }
        $this->meta[$path] = array_merge(ake($this->meta, $path, []), $values);

        return true;
    }

    public function getMeta(string $path, ?string $key = null): array|false|string
    {
        if ($info = $this->info($path)) {
            return $key ? ake(ake($info, 'meta'), $key) : ake($info, 'meta');
        }

        return false;
    }

    public function preview_uri(string $path): false|string
    {
        if ($info = $this->info($path)) {
            return ake($info, 'previewLink');
        }

        return false;
    }

    public function directURL(string $path): false|string
    {
        if ($info = $this->info($path)) {
            return ake($info, 'link');
        }

        return false;
    }

    public function previewURL(string $path, array $params = []): false|string
    {
        return false;
    }

    public function authorise(?string $redirect_uri = null): bool
    {
        return false;
    }

    public function authorised(): bool
    {
        return false;
    }

    public function buildAuthURL(?string $callback_url = null): ?string
    {
        return null;
    }

    public function touch(string $path): bool
    {
        return false;
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

    public function find(?string $search = null, string $path = '/', bool $case_insensitive = false): array|false
    {
        return false;
    }

    public function fsck(bool $skip_root_reload = false): bool
    {
        return false;
    }

    /**
     * @param array<mixed> $params
     * @param array<mixed> $mime_parts
     *
     * @return array<mixed>|false
     */
    private function request(string $cmd, array $params = [], array $mime_parts = []): array|false
    {
        $request = new Request($this->options['url'], 'POST');
        if (count($params) > 0) {
            $request->populate($params);
        }
        $request['cmd'] = $cmd;
        if (count($mime_parts) > 0) {
            foreach ($mime_parts as $part) {
                if (2 == !count($part)) {
                    continue;
                }
                $request->addMultipart($part[0], $part[1]);
            }
        }
        $response = $this->client->send($request);
        if (200 == $response->status) {
            return json_decode($response->body, true);
        }

        return false;
    }
}
