<?php

declare(strict_types=1);

namespace Hazaar\File;

use Hazaar\Controller\Exception\BrowserRootNotFound;
use Hazaar\Controller\Response\File as FileResponse;
use Hazaar\File;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;

class BrowserConnector
{
    /**
     * @var array<Manager>
     */
    private array $sources = [];
    private string $url;

    /**
     * @var array<string>
     */
    private array $allowPreview;
    private ?\Closure $progressCallBack = null;

    /**
     * @param array<string> $allowPreview List of mime types that are allowed to be previewed
     */
    public function __construct(?string $url = null, ?array $allowPreview = null)
    {
        $this->url = $url;
        $this->allowPreview = $allowPreview;
    }

    public function setProgressCallback(callable|\Closure $callback): void
    {
        $this->progressCallBack = $callback;
    }

    public function addSource(string $id, Manager|string $source, ?string $name = null): bool
    {
        if ($source instanceof Manager) {
            if (!$name) {
                $name = ucfirst($id);
            }
        } else {
            if (!$name) {
                $name = basename($source);
            }
            if (!file_exists($source)) {
                throw new BrowserRootNotFound();
            }
            $source = new Manager('local', ['root' => rtrim($source, '/\\')]);
        }
        $source->setOption('name', $name);
        $this->sources[$id] = $source;

        return true;
    }

    public function authorised(): bool|string
    {
        foreach ($this->sources as $id => $source) {
            if (!$source->authorised()) {
                return $id;
            }
        }

        return true;
    }

    public function authorise(string $sourceName, ?string $redirect_uri = null): bool
    {
        if (!($source = ($this->sources[$sourceName] ?? null))) {
            return false;
        }

        return $source->authorise($redirect_uri);
    }

    public function source(string $target): false|Manager
    {
        if (array_key_exists($target, $this->sources)) {
            return $this->sources[$target];
        }
        $raw = base64url_decode($target);
        if (($pos = strpos($raw, ':')) > 0) {
            $source = substr($raw, 0, $pos);
        } else {
            $source = $raw;
        }

        return $this->sources[$source] ?? false;
    }

    public function path(string $target): false|string
    {
        $raw = base64url_decode($target);
        if (($pos = strpos($raw, ':')) > 0) {
            return substr($raw, $pos + 1);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function info(Manager $source, Dir|File $file): array
    {
        $is_dir = $file instanceof Dir || $file->isDir();
        $parent = ('/' === $file->fullpath()) ? $this->target($source) : $this->target($source, rtrim($file->dirname(), '/').'/');
        $path = $source->fixPath($file->dirname(), $file->basename());
        $fileId = $this->target($source, $is_dir ? rtrim($path, '/').'/' : $path);
        $linkURL = rtrim($this->url, '/').'/'.$source->name.rtrim($file->dirname(), '/').'/'.$file->basename();
        $downloadURL = $linkURL.'?download=true';
        $info = [
            'id' => $fileId,
            'kind' => $file->type(),
            'name' => $file->basename(),
            'path' => $file->fullpath(),
            'link' => $linkURL,
            'downloadLink' => $downloadURL,
            'parent' => $parent,
            'modified' => $file->mtime(),
            'size' => $file->size(),
            'mime' => (('file' == $file->type()) ? $file->mimeContentType() : 'dir'),
            'read' => $file->isReadable(),
            'write' => $file->isWritable(),
        ];
        if ($is_dir) {
            $info['dirs'] = 0;
            $dir = $source->dir($file->fullpath());
            while (($file = $dir->read()) != false) {
                if ($file->isDir()) {
                    ++$info['dirs'];
                }
            }
        } elseif ($file->isReadable() && preg_match_array($this->allowPreview, $info['mime'])) {
            $info['previewLink'] = rtrim($this->url, '/').'/'.$source->name.rtrim($file->dirname(), '/').'/'.$file->basename().'?width={$w}&height={$h}&crop=true';
        }

        return $info;
    }

    /**
     * @return array<mixed>|false
     */
    public function tree(?string $target = null, ?int $depth = null): array|false
    {
        $tree = [];
        if ($target) {
            if (!count($this->sources) > 0) {
                return false;
            }
            if (!$source = $this->source($target)) {
                return false;
            }
            $path = trim($this->path($target));
            $dir = $source->dir($path);
            if ('/' === substr($path, -1)) {
                while (($file = $dir->read()) !== false) {
                    if (!$file->isDir()) {
                        continue;
                    }
                    $tree[] = $this->info($source, $file);
                    if ($depth > 0 || null === $depth) {
                        $sub = $this->tree($this->target($source, $file->fullpath()), (null !== $depth) ? $depth - 1 : null);
                        $tree = array_merge($tree, $sub);
                    }
                }
            } else {
                $tree = [$this->info($source, $dir)];
            }
        } else {
            foreach ($this->sources as $id => $source) {
                if (false === $source->refresh()) {
                    return false;
                }
                $root = $source->get('/');
                $info = $this->info($source, $root);
                $info['name'] = $source->getOption('name');
                $info['expanded'] = (array_search($id, array_keys($this->sources)) > 0) ? false : true;
                $tree[] = $info;
                if ($depth > 0 || null === $depth) {
                    $sub = $this->tree($this->target($source, $root->fullpath()), (null !== $depth) ? $depth - 1 : null);
                    $tree = array_merge($tree, $sub);
                }
            }
        }

        return $tree;
    }

    /**
     * @return array<mixed>|false
     */
    public function open(
        string $target,
        bool $tree = false,
        int $depth = 1,
        ?string $filter = null,
        bool $with_meta = false
    ): array|false {
        if (!count($this->sources) > 0) {
            return false;
        }
        if (!$target) {
            $target = $this->target(array_keys($this->sources)[0], '/');
        }
        if (!$source = $this->source($target)) {
            return false;
        }
        $source->refresh();
        $files = [];
        $path = rtrim($source->fixPath($this->path($target)), '/').'/';
        $dir = $source->dir($path);
        while (($file = $dir->read()) !== false) {
            if (!$file->isReadable()) {
                continue;
            }
            if ($filter && !preg_match('/'.$filter.'/', $file->mimeContentType())) {
                continue;
            }
            $info = $this->info($source, $file);
            if ($with_meta) {
                $info['meta'] = $file->getMeta();
            }
            $files[] = $info;
        }
        $result = [
            'cwd' => [
                'id' => $this->target($source, $path),
                'name' => $path,
                'source' => array_search($source, $this->sources),
            ],
            'sys' => [
                'max_upload_size' => min(bytes_str(ini_get('upload_max_filesize')), bytes_str(ini_get('post_max_size'))),
            ],
            'files' => $files,
        ];
        if (true === boolify($tree)) {
            $result['tree'] = $this->tree($target, $depth);
        }

        return $result;
    }

    public function get(string $target): FileResponse
    {
        $source = $this->source($target);
        $path = $this->path($target);
        $file = $source->get($path);
        $response = new FileResponse($file);
        $response->setDownloadable(true);

        return $response;
    }

    public function getFile(string $source, string $path = '/'): false|File
    {
        if ($source = $this->source($source)) {
            return $source->get($path);
        }

        return false;
    }

    public function getFileByPath(string $path): false|File
    {
        list($source, $path) = explode('/', $path, 2);
        if ($source = $this->source($source)) {
            return $source->get($path);
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    public function mkdir(string $parent, string $name): array
    {
        $source = $this->source($parent);
        $path = rtrim($this->path($parent), '/').'/'.$name;
        if ($source->mkdir($path)) {
            return ['tree' => [$this->info($source, $source->get($path))]];
        }

        return ['ok' => false];
    }

    /**
     * @return array<mixed>
     */
    public function rmdir(string $target, bool $recurse = false): array
    {
        $source = $this->source($target);
        $path = $this->path($target);
        $result = $source->rmdir($path, $recurse);

        return ['ok' => $result];
    }

    /**
     * @param array<string>|string $target
     *
     * @return array<mixed>
     */
    public function unlink(array|string $target): array
    {
        if (!is_array($target)) {
            $target = [$target];
        }
        $out = ['items' => []];
        foreach ($target as $item) {
            $source = $this->source($item);
            $path = $this->path($item);
            $result = $source->unlink($path);
            if ($result) {
                $out['items'][] = $item;
            }
        }

        return $out;
    }

    /**
     * @return array<mixed>
     */
    public function copy(string $from, string $to): array
    {
        $srcSource = $this->source($from);
        $srcPath = $this->path($from);
        $files = $srcSource->find(null, $srcPath);
        if ($this->progressCallBack) {
            call_user_func_array($this->progressCallBack, ['copy', ['init' => count($files)]]);
        }
        $dstSource = $this->source($to);
        $dstPath = $this->path($to);
        $result = $dstSource->copy($srcPath, $dstPath, true, $srcSource, $this->progressCallBack);
        if ($result) {
            $out = [];
            $file = $dstSource->get(rtrim($dstPath, '/').'/'.basename($srcPath));
            $info = $this->info($dstSource, $file);
            if ('dir' == $info['kind']) {
                $out['tree'] = [$info];
            } else {
                $out['items'] = [$info];
            }

            return $out;
        }

        return ['ok' => false];
    }

    /**
     * @return array<mixed>|false
     */
    public function move(string $from, string $to): array|false
    {
        $srcSource = $this->source($from);
        $srcPath = $this->path($from);
        $dstSource = $this->source($to);
        $dstPath = $this->path($to);
        $result = $dstSource->move($srcPath, $dstPath, $srcSource);
        if ($result) {
            $out = [];
            $file = $dstSource->get(rtrim($dstPath, '/').'/'.basename($srcPath));
            $info = $this->info($dstSource, $file);
            if ('dir' == $info['kind']) {
                $out['rmdir'] = [$from];
                $out['tree'] = [$info];
            } else {
                $out['unlink'] = [$from];
                $out['items'] = [$info];
            }

            return $out;
        }

        return ['ok' => false];
    }

    /**
     * @return array<mixed>
     */
    public function rename(string $target, string $name, bool $with_meta = false): array
    {
        $manager = $this->source($target);
        $path = $this->path($target);
        $new = rtrim(dirname($path), '/').'/'.$name;
        if ($manager->move($path, $new)) {
            $file = $manager->get($new);
            $info = $this->info($manager, $file);
            if (true === $with_meta) {
                $info['meta'] = $file->getMeta();
            }

            return ['ok' => true, 'rename' => [$target => $info]];
        }

        return ['ok' => false];
    }

    /**
     * @param array<string> $file
     *
     * @return array<mixed>
     */
    public function upload(string $parent, array $file, ?string $relativePath = null): array|false
    {
        if (!(array_key_exists('tmp_name', $file) && array_key_exists('name', $file) && array_key_exists('type', $file))) {
            return false;
        }
        if (!$file['tmp_name']) {
            return false;
        }
        $source = $this->source($parent);
        $path = rtrim($this->path($parent), '/').'/';
        $info = [];
        if ($relativePath) {
            $parts = explode('/', dirname($relativePath));
            for ($i = 0; $i < count($parts); ++$i) {
                $newPath = $path.implode('/', array_slice($parts, 0, $i + 1));
                if (!$source->exists($newPath)) {
                    if (!$source->mkdir($newPath)) {
                        throw new \Exception('Could not create parent directories');
                    }
                    $newDir = $source->get($newPath);
                    $info['tree'][] = $this->info($source, $newDir);
                }
            }
            $path .= dirname($relativePath).'/';
        }
        $result = $source->upload($path, $file);
        if ($result) {
            $fullpath = $path.$file['name'];
            $f = $source->get($fullpath);
            $info['file'] = $this->info($source, $f);

            return $info;
        }

        return ['ok' => false];
    }

    /**
     * @return array<mixed>
     */
    public function getMeta(string $target, ?string $key = null): array
    {
        $source = $this->source($target);
        $path = $this->path($target);
        if ($meta = $source->getMeta($path, $key)) {
            return ['ok' => true, 'value' => $meta];
        }

        return ['ok' => false];
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    public function setMeta(string $target, array $values): array
    {
        $source = $this->source($target);
        $path = $this->path($target);
        if ($source->setMeta($path, $values)) {
            return ['ok' => true];
        }

        return ['ok' => false];
    }

    /**
     * @return array<mixed>
     */
    public function snatch(string $url, string $target): array
    {
        $out = ['ok' => false, 'reason' => 'unknown'];
        $client = new Client();
        $request = new Request($url);
        $response = $client->send($request);
        if (200 == $response->status) {
            $source = $this->source($target);
            $path = rtrim($this->path($target), '/').'/'.basename($url);
            if ($source->write($path, $response->body, $response->getHeader('content-type'))) {
                $file = $source->get($path);
                $items = [
                    $this->info($source, $file),
                ];

                return ['ok' => true, 'items' => $items];
            }
            $out['reason'] = 'Downloaded OK, but unable to write file to storage server.';
        } else {
            $out['reason'] = 'Remote server returned HTTP response '.$response->status;
        }

        return $out;
    }

    /**
     * @return array<mixed>
     */
    public function search(string $target, string $query): array
    {
        $source = $this->source($target);
        $path = $this->path($target);
        $list = $source->find($query, $path, true);
        foreach ($list as &$item) {
            $item = $this->info($source, $source->get($item));
        }

        return $list;
    }

    private function target(Manager|string $source, ?string $path = null): string
    {
        if ($source instanceof Manager) {
            $source = array_search($source, $this->sources);
        }

        return base64url_encode($source.':'.$path);
    }
}
