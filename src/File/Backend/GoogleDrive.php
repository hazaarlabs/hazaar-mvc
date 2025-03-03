<?php

declare(strict_types=1);

namespace Hazaar\File\Backend;

use Hazaar\Cache\Adapter;
use Hazaar\File\Backend\Exception\GoogleDriveError;
use Hazaar\File\Interface\Backend as BackendInterface;
use Hazaar\File\Interface\Driver as DriverInterface;
use Hazaar\File\Manager;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;

class GoogleDrive extends Client implements BackendInterface, DriverInterface
{
    public string $separator = '/';

    protected Manager $manager;

    /**
     * @var array<string>
     */
    private array $scope = [
        'https://www.googleapis.com/auth/drive',
    ];

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
     * @var array<string>
     */
    private array $meta_items = [
        'kind',
        'id',
        'title',
        'parents',
        'labels',
        'copyable',
        'editable',
        'mimeType',
        'createdDate',
        'modifiedDate',
        'fileSize',
        'downloadUrl',
        'exportLinks',
        'thumbnailLink',
        'webContentLink',
        'md5Checksum',
    ];
    private int $cursor;
    private string $oauth2ID;

    public function __construct(array $options, Manager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
        $this->options = array_merge([
            'cache_backend' => 'file',
            'oauth2' => ['access_token' => null],
            'refresh_attempts' => 5,
            'maxResults' => 100,
            'root' => '/',
        ], $options);
        if (!isset($this->options['client_id'], $this->options['client_secret'])) {
            throw new GoogleDriveError('Google Drive filesystem backend requires both client_id and client_secret.');
        }
        $cacheOps = [
            'use_pragma' => false,
            'namespace' => 'googledrive_'.$this->options['client_id'],
        ];
        $this->cache = new Adapter($this->options['cache_backend'], $cacheOps);
        $this->oauth2ID = 'oauth2_data::'.md5(implode('', $this->scope));
        $this->reload();
    }

    public function __destruct()
    {
        $this->cache->set('meta', $this->meta);
        $this->cache->set('cursor', $this->cursor);
    }

    public static function label(): string
    {
        return 'GoogleDrive';
    }

    public function reload(): bool
    {
        if ($oauth2 = $this->cache->get($this->oauth2ID)) {
            $this->options['oauth2'] = $oauth2;
        }
        if (($cursor = $this->cache->get('cursor')) !== false) {
            $this->cursor = $cursor;
        }
        if (($meta = $this->cache->get('meta')) !== false) {
            $this->meta = $meta;
        }

        return true;
    }

    public function reset(): bool
    {
        unset($this->options['oauth2']);
        $this->meta = [];
        $this->cache->remove($this->oauth2ID);
        $this->cache->remove('cursor');
        $this->cache->remove('meta');

        return true;
    }

    public function authorise(?string $redirect_uri = null): bool
    {
        if (($code = ake($_REQUEST, 'code')) && ($state = ake($_REQUEST, 'state'))) {
            if ($state != $this->cache->pull('oauth2_state')) {
                throw new \Exception('Bad state!');
            }
            $request = new Request('https://accounts.google.com/o/oauth2/token', 'POST');
            $request['code'] = $code;
            $request['redirect_uri'] = (string) $redirect_uri;
            $request['grant_type'] = 'authorization_code';
        } elseif ($this->options['oauth2']->has('refresh_token') && $refresh_token = $this->options['oauth2']->get('refresh_token')) {
            $request = new Request('https://www.googleapis.com/oauth2/v3/token', 'POST');
            $request['refresh_token'] = $refresh_token;
            $request['grant_type'] = 'refresh_token';
        } else {
            return $this->authorised();
        }
        $request['client_id'] = $this->options['client_id'];
        $request['client_secret'] = $this->options['client_secret'];
        $response = $this->send($request);
        if (200 !== $response->status) {
            return false;
        }
        if ($auth = json_decode($response->body, true)) {
            $this->options['oauth2']->extend($auth);
            $this->cache->set($this->oauth2ID, $this->options['oauth2']->toArray(), -1);

            return true;
        }

        return false;
    }

    public function authorised(): bool
    {
        return isset($this->options['oauth2'])
            && isset($this->options['oauth2']['access_token'])
            && null != $this->options['oauth2']['access_token'];
    }

    public function buildAuthURL(?string $redirect_uri = null): string
    {
        $state = md5(uniqid());
        $this->cache->set('oauth2_state', $state, 300);
        $params = [
            'response_type=code',
            'access_type=offline',
            'approval_prompt=force',
            'client_id='.$this->options['client_id'],
            'scope='.implode(' ', $this->scope),
            'redirect_uri='.$redirect_uri,
            'state='.$state,
        ];

        return 'https://accounts.google.com/o/oauth2/auth?'.implode('&', $params);
    }

    public function refresh(bool $reset = false): bool
    {
        if ($reset || 0 == count($this->meta)) {
            $this->meta = [];
            $request = new Request('https://www.googleapis.com/drive/v2/changes', 'GET');
            $response = $this->sendRequest($request);
            $this->cursor = (int) $response['largestChangeId'];
            $request = new Request('https://www.googleapis.com/drive/v2/files/root', 'GET');
            $response = $this->sendRequest($request);
            $this->meta[$response['id']] = array_intersect_key($response, array_flip($this->meta_items));
            $request = new Request('https://www.googleapis.com/drive/v2/files', 'GET');
            if (isset($this->options['maxResults'])) {
                $request['maxResults'] = $this->options['maxResults'];
            }
            while (true) {
                $response = $this->sendRequest($request);
                if (!$response) {
                    return false;
                }
                foreach ($response['items']->toArray() as $item) {
                    $this->meta[$item['id']] = array_intersect_key($item, array_flip($this->meta_items));
                }
                if (!isset($response['nextPageToken'])) {
                    break;
                }
                $request['pageToken'] = $response['nextPageToken'];
            }

            return true;
        }
        $request = new Request('https://www.googleapis.com/drive/v2/changes?pageToken='.($this->cursor + 1), 'GET');
        $response = $this->sendRequest($request);
        $this->cursor = $response['largestChangeId'];
        if (!$response) {
            return false;
        }
        $items = [];
        $deleted = [];
        foreach ($response['items']->toArray() as $item) {
            if (true === $item['deleted'] && array_key_exists($item['fileId'], $this->meta)) {
                $items = array_merge($items, $this->resolveItem($this->meta[$item['fileId']]));
                $deleted[] = $item['fileId'];
            } elseif (array_key_exists('file', $item)) {
                $file = array_intersect_key($item['file'], array_flip($this->meta_items));
                $this->meta[$item['fileId']] = $file;
                $items = array_merge($items, $this->resolveItem($file));
            }
        }
        foreach ($deleted as $fileId) {
            unset($this->meta[$fileId]);
        }

        return true;
    }

    // Get a directory listing
    public function scandir(
        string $path,
        ?string $regex_filter = null,
        int $sort = SCANDIR_SORT_ASCENDING,
        bool $show_hidden = false,
        ?string $relative_path = null
    ): array|bool {
        $parent = $this->resolvePath($path);
        $items = [];
        foreach ($this->meta as $item) {
            if (!(array_key_exists('parents', $item) && $item['parents']) || $item['labels']['trashed']) {
                continue;
            }
            if ($this->itemHasParent($item, $parent['id'])) {
                $items[] = $item['title'];
            }
        }

        return $items;
    }

    // Check if file/path exists
    public function exists(string $path): bool
    {
        if ($item = $this->resolvePath($path)) {
            return !ake($item['labels'], 'trashed', false);
        }

        return false;
    }

    public function realpath(string $path): ?string
    {
        return $path;
    }

    public function isReadable(string $path): bool
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }

        return ake($item, 'copyable', false);
    }

    public function isWritable(string $path): bool
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }

        return ake($item, 'editable', false);
    }

    // TRUE if path is a directory
    public function isDir(string $path): bool
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }

        return 'application/vnd.google-apps.folder' == $item['mimeType'];
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
        if (!($item = $this->resolvePath($path))) {
            return false;
        }
        if ('application/vnd.google-apps.folder' == $item['mimeType']) {
            return 'dir';
        }

        return 'file';
    }

    // Returns the file modification time
    public function filectime(string $path): false|int
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }

        return strtotime($item['createdDate']);
    }

    // Returns the file modification time
    public function filemtime(string $path): false|int
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }

        return strtotime($item['modifiedDate']);
    }

    public function touch(string $path): bool
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }
        $request = new Request('https://www.googleapis.com/drive/v2/files/'.$item['id'], 'PATCH', 'application/json');
        $request['modifiedDate'] = date('c');
        $this->sendRequest($request, false);

        return true;
    }

    // Returns the file modification time
    public function fileatime(string $path): false|int
    {
        return false;
    }

    public function filesize(string $path): false|int
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }

        return ake($item, 'fileSize', 0);
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
        $item = $this->resolvePath($path);
        if (false === $item) {
            return false;
        }
        if (!($parent = $this->resolvePath(dirname($path)))) {
            return false;
        }
        if (count($item['parents']) > 1) {
            $request = new Request('https://www.googleapis.com/drive/v2/files/'.$item['id'].'/parents/'.$parent['id'], 'DELETE', 'application/json');
            $this->sendRequest($request, false);
        } else {
            $request = new Request('https://www.googleapis.com/drive/v2/files/'.$item['id'], 'DELETE');
            $this->sendRequest($request, false);
        }

        return true;
    }

    public function mimeContentType(string $path): ?string
    {
        if (!($item = $this->resolvePath($path))) {
            return null;
        }

        return ake($item, 'mimeType');
    }

    public function md5Checksum(string $path): ?string
    {
        if (!($item = $this->resolvePath($path))) {
            return null;
        }

        return ake($item, 'md5Checksum');
    }

    // Create a directory
    public function mkdir(string $path): bool
    {
        if (!($parent = $this->resolvePath(dirname($path)))) {
            return false;
        }
        $request = new Request('https://www.googleapis.com/drive/v2/files', 'POST', 'application/json');
        $request['title'] = basename($path);
        $request['parents'] = [['id' => $parent['id']]];
        $request['mimeType'] = 'application/vnd.google-apps.folder';
        $response = $this->sendRequest($request);
        if ($response) {
            $this->meta[$response['id']] = $response;

            return true;
        }

        return false;
    }

    public function rmdir(string $path, bool $recurse = false): bool
    {
        $path = implode($this->separator, $this->resolveItem($this->resolvePath($path)));
        if (!$this->exists($path)) {
            return false;
        }
        if ($recurse) {
            $dir = $this->scandir($path, null, SCANDIR_SORT_ASCENDING, true);
            foreach ($dir as $file) {
                if ('.' == $file || '..' == $file) {
                    continue;
                }
                $fullpath = $path.$this->separator.$file;
                if ($this->isDir($fullpath)) {
                    $this->rmdir($fullpath, true);
                } else {
                    $this->unlink($fullpath);
                }
            }
        }

        return $this->unlink($path);
    }

    // Copy a file from src to dst
    public function copy(string $src, string $dst, bool $recursive = false): bool
    {
        if (!($item = $this->resolvePath($src))) {
            return false;
        }
        if (!($parent = $this->resolvePath($dst))) {
            return false;
        }
        $request = new Request('https://www.googleapis.com/drive/v2/files/'.$item['id'].'/copy', 'POST', 'application/json');
        $request['title'] = $item['title'];
        $request['parents'] = [['id' => $parent['id']]];
        $response = $this->sendRequest($request);
        if ($response) {
            $this->meta[$response['id']] = $response;

            return true;
        }

        return false;
    }

    public function link(string $src, string $dst): bool
    {
        if (!($item = $this->resolvePath($src))) {
            return false;
        }
        if (!($parent = $this->resolvePath($dst))) {
            return false;
        }
        $request = new Request('https://www.googleapis.com/drive/v2/files/'.$item['id'].'/parents/'.$parent['id'], 'POST', 'application/json');
        $this->sendRequest($request, false);

        return true;
    }

    // Move a file from src to dst
    public function move(string $src, string $dst): bool
    {
        if (!($item = $this->resolvePath($src))) {
            return false;
        }
        if (!($srcParent = $this->resolvePath(dirname($src)))) {
            return false;
        }
        if (!($dstParent = $this->resolvePath($dst))) {
            return false;
        }
        $request = new Request('https://www.googleapis.com/drive/v2/files/'.$item['id'].'/parents', 'POST', 'application/json');
        $request->populate(['id' => $dstParent['id']]);
        $response = $this->sendRequest($request);
        if ($response) {
            $this->meta[$item['id']]['parents'][] = $request->toArray();
            $request = new Request('https://www.googleapis.com/drive/v2/files/'.$item['id'].'/parents/'.$srcParent['id'], 'DELETE', 'application/json');
            $this->sendRequest($request, false);

            return true;
        }

        return false;
    }

    // Read the contents of a file
    public function read(string $path, int $offset = -1, ?int $maxlen = null): false|string
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }
        if (!($downloadUrl = ake($item, 'downloadUrl'))) {
            if ($exportLinks = ake($item, 'exportLinks')) {
                if (array_key_exists('application/rtf', $exportLinks)) {
                    $downloadUrl = $exportLinks['application/rtf'];
                } elseif (array_key_exists('application/pdf', $exportLinks)) {
                    $downloadUrl = $exportLinks['application/pdf'];
                } else {
                    return false;
                }
            }
        }
        $request = new Request($downloadUrl, 'GET');

        return $this->sendRequest($request, false);
    }

    // Write the contents of a file
    public function write(string $file, string $data, ?string $content_type = null, bool $overwrite = false): ?int
    {
        if (!$overwrite && $this->exists($file)) {
            return null;
        }
        if (!($parent = $this->resolvePath(dirname($file)))) {
            return null;
        }
        $request = new Request('https://www.googleapis.com/upload/drive/v2/files?uploadType=multipart', 'POST');
        $request->addMultipart(['title' => basename($file), 'parents' => [['id' => $parent['id']]]], 'application/json');
        $request->addMultipart($data, $content_type);
        $response = $this->sendRequest($request);
        if ($response) {
            $this->meta[$response['id']] = $response;

            return strlen($data);
        }

        return null;
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
     * @param array<mixed> $values
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
     * @param array<string,int|string> $param
     */
    public function previewURL(string $path, array $param = []): false|string
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }
        if (!($link = ake($item, 'thumbnailLink'))) {
            return false;
        }
        if (($pos = strrpos($link, '=')) > 0) {
            $link = substr($link, 0, $pos);
        }
        $link .= '=w{$w}-h{$h}-p';

        return $link;
    }

    public function directURL(string $path): false|string
    {
        if (!($item = $this->resolvePath($path))) {
            return false;
        }

        return str_replace('&export=download', '', ake($item, 'webContentLink'));
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
     * @return array<mixed>|string
     */
    private function sendRequest(Request $request, bool $isMeta = true): array|string
    {
        $count = 0;
        while (++$count) {
            if ($count > $this->options['refresh_attempts']) {
                throw new \Exception('Too many refresh attempts!');
            }
            $request->setHeader('Authorization', $this->options['oauth2']['token_type'].' '.$this->options['oauth2']['access_token']);
            $response = $this->send($request);
            if ($response->status >= 200 && $response->status < 206) {
                break;
            }
            if (401 == $response->status) {
                if (!$this->authorise()) {
                    throw new \Exception('Unable to refresh access token!');
                }
            } else {
                if ('application/json' == $response->getHeader('content-type')) {
                    $meta = $response->body;
                    if (isset($meta['error'])) {
                        $err = $meta['error'];
                    } else {
                        $err = 'Unknown error!';
                    }
                    $message = $err->message;
                    $code = $err->code;
                } else {
                    $message = $response->body;
                    $code = (int) $response->status;
                }

                throw new \Exception($message, $code);
            }
        }
        if (true == $isMeta) {
            $meta = $response->body;
            if (isset($meta['error'])) {
                throw new \Exception($meta['error']);
            }
        } else {
            $meta = $response->body;
        }

        return $meta;
    }

    /**
     * @param array<mixed> $item
     */
    private function itemHasParent(array $item, string $parentId): bool
    {
        if (!(array_key_exists('parents', $item) && is_array($item['parents']))) {
            return false;
        }
        foreach ($item['parents'] as $itemParent) {
            if ($itemParent['id'] == $parentId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<mixed>|false
     */
    private function resolvePath(string $path): array|false
    {
        $path = '/'.trim($this->options['root'], '/').'/'.ltrim($path, '/');
        $parent = null;
        foreach ($this->meta as $item) {
            if (0 === count($item['parents'])) {
                $parent = $item;

                break;
            }
        }
        if (!$parent) {
            return false;
        } // This should never happen!
        if ('/' !== $path) {
            $parts = explode('/', $path);
            // Paths have a forward slash on the start and end so we need to drop the first and last elements.
            array_shift($parts);
            foreach ($parts as $part) {
                if (!$part) {
                    continue;
                }
                $id = $parent['id'];
                $parent = null;
                foreach ($this->meta as $item) {
                    if (array_key_exists('title', $item) && $item['title'] === $part && $this->itemHasParent($item, $id)) {
                        $parent = $item;
                    }
                }
                if (!$parent) {
                    return false;
                }
            }
        }

        return $parent;
    }

    /**
     * @param array<string,int|string> $item
     *
     * @return array<string>
     */
    private function resolveItem(array $item): array
    {
        $path = [];
        if ($parents = ake($item, 'parents')) {
            foreach ($parents as $parentRef) {
                if (!($parent = ake($this->meta, $parentRef['id']))) {
                    continue;
                }
                $parentPaths = $this->resolveItem($parent);
                foreach ($parentPaths as $index => $value) {
                    $path[] = rtrim($value, '/').'/'.$item['title'];
                }
            }
        } else {
            $path[] = '/';
        }

        return $path;
    }
}
