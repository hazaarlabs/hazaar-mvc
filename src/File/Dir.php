<?php

declare(strict_types=1);

namespace Hazaar\File;

use Hazaar\File;
use Hazaar\File\Backend\Interfaces\Backend;
use Hazaar\HTTP\URL;

define('HZ_SYNC_DIR', 1);
define('HZ_SYNC_DIR_COMPLETE', 2);
define('HZ_SYNC_FILE', 3);
define('HZ_SYNC_FILE_UPDATE', 4);
define('HZ_SYNC_FILE_COMPLETE', 5);
define('HZ_SYNC_ERROR', 6);

class Dir
{
    protected string $path;
    protected Backend $backend;
    protected Manager $manager;

    /**
     * @var array<File>
     */
    protected ?array $files = null;
    protected bool $allow_hidden = false;
    protected ?URL $__media_uri = null;
    protected ?string $relative_path = null;

    public function __construct(File|string $path, ?Manager $manager = null, ?string $relative_path = null)
    {
        if ($path instanceof File) {
            $manager = $path->getManager();
        } elseif (!$manager) {
            $manager = new Manager();
        }
        $this->manager = $manager;
        $this->path = $this->manager->fixPath($path);
        if ($relative_path) {
            $this->relative_path = rtrim(str_replace('\\', '/', $relative_path), '/');
        }
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function backend(): string
    {
        return strtolower((new \ReflectionClass($this->manager))->getShortName());
    }

    public function getManager(): Manager
    {
        return $this->manager;
    }

    /**
     * @param array<string, int|string> $values
     */
    public function setMeta(array $values): bool
    {
        return $this->manager->setMeta($this->path, $values);
    }

    public function getMeta(?string $key = null): mixed
    {
        return $this->manager->getMeta($this->path, $key);
    }

    public function toString(): string
    {
        return $this->path();
    }

    public function path(?string $suffix = null): string
    {
        return $this->path.($suffix ? '/'.$suffix : '');
    }

    public function fullpath(?string $suffix = null): string
    {
        return $this->path($suffix);
    }

    /**
     * Get the relative path of the directory.
     *
     * If the file was returned from a [[Hazaar\File\Dir]] object, then it will have a stored
     * relative path.  Otherwise any file/path can be provided in the form of another [[Hazaar\File\\
     * object, [[Hazaar\File\Dir]] object, or string path, and the relative path to the file will
     * be returned.
     */
    public function relativepath(null|Dir|File|string $path = null): false|string
    {
        if (null !== $path) {
            if ($path instanceof File) {
                $path = $path->dirname();
            } elseif ($path instanceof Dir) {
                $path = $path->fullpath();
            }
            $source_path = explode('/', trim(str_replace('\\', '/', dirname($this->path)), '/'));
            $path = explode('/', trim(str_replace('\\', '/', $path), '/'));
            $index = 0;
            while (isset($source_path[$index], $path[$index])
                && $source_path[$index] === $path[$index]) {
                ++$index;
            }
            $diff = count($source_path) - $index;

            return implode('/', array_merge(array_fill(0, $diff, '..'), array_slice($path, $index)));
        }

        if (!$this->relative_path) {
            return $this->fullpath();
        }
        $dir_parts = explode('/', $this->dirname());
        $rel_parts = explode('/', $this->relative_path);
        $path = null;
        foreach ($dir_parts as $index => $part) {
            if (array_key_exists($index, $rel_parts) && $rel_parts[$index] === $part) {
                continue;
            }
            $dir_parts = array_slice($dir_parts, $index);
            if (($count = count($rel_parts) - $index) > 0) {
                $dir_parts = array_merge(array_fill(0, $count, '..'), $dir_parts);
            }
            $path = implode('/', $dir_parts);

            break;
        }

        return ($path ? $path.'/' : '').$this->basename();
    }

    public function setRelativePath(string $path): void
    {
        $this->relative_path = $path;
    }

    public function realpath(): string
    {
        return $this->manager->realpath($this->path);
    }

    public function dirname(): string
    {
        return str_replace('\\', '/', dirname($this->path));
    }

    public function name(): string
    {
        return pathinfo($this->path, PATHINFO_BASENAME);
    }

    public function extension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    public function basename(): string
    {
        return basename($this->path);
    }

    public function size(): int
    {
        return $this->manager->filesize($this->path);
    }

    public function type(): string
    {
        return $this->manager->filetype($this->path);
    }

    public function exists(?string $filename = null): bool
    {
        return $this->manager->exists(rtrim($this->path, '/').($filename ? '/'.$filename : ''));
    }

    public function isReadable(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->isReadable($this->path);
    }

    public function isWritable(): bool
    {
        return $this->manager->isWritable($this->path);
    }

    public function isFile(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->isFile($this->path);
    }

    public function isDir(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->isDir($this->path);
    }

    public function isLink(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->isLink($this->path);
    }

    public function parent(): Dir
    {
        return new Dir($this->dirname(), $this->manager);
    }

    public function ctime(): false|int
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->filectime($this->path);
    }

    public function mtime(): false|int
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->filemtime($this->path);
    }

    public function touch(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->touch($this->path);
    }

    public function atime(): false|int
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->fileatime($this->path);
    }

    public function allowHidden(bool $toggle = true): void
    {
        $this->allow_hidden = $toggle;
    }

    public function create(bool $recursive = false): bool
    {
        if (true !== $recursive) {
            return $this->manager->mkdir($this->path);
        }
        $parents = [];
        $last = $this->path;
        while (!$this->manager->exists($last)) {
            $parents[] = $last;
            $last = $this->manager->fixPath(dirname($last));
            if ('/' === $last) {
                break;
            }
        }
        while ($parent = array_pop($parents)) {
            if (!$this->manager->mkdir($parent)) {
                return false;
            }
        }

        return true;
    }

    public function rename(string $newname): bool
    {
        return $this->manager->move($this->path, $this->dirname().'/'.$newname, null);
    }

    /**
     * Delete the directory, optionally removing all it's contents.
     *
     * Executing this method will simply delete or "unlink" the directory.  Normally it must be empty
     * to succeed.  However specifying the $recursive parameter as TRUE will delete everything inside
     * the directory, recursively (obviously).
     */
    public function delete(bool $recursive = false): bool
    {
        return $this->manager->rmdir($this->path, $recursive);
    }

    /**
     * File::unlink() compatible delete that removes dir and all contents (ie: recursive).
     */
    public function unlink(): bool
    {
        return $this->delete(true);
    }

    public function isEmpty(): bool
    {
        return $this->manager->isEmpty($this->path);
    }

    /**
     * Empty a directory of all it's contents.
     *
     * This is the same as calling delete(true) except that the directory itself is not deleted.
     *
     * By default hidden files are not deleted.  This is for protection.  You can choose to delete them
     * as well by setting $include_hidden to true.
     *
     * @param mixed $include_hidden also delete hidden files
     *
     * @return bool
     */
    public function empty($include_hidden = false)
    {
        $org = null;
        if ($include_hidden && !$this->allow_hidden) {
            $org = $this->allow_hidden;
            $this->allow_hidden = true;
        }
        $this->rewind();
        while ($file = $this->read()) {
            $file->unlink();
        }
        if (null !== $org) {
            $this->allow_hidden = $org;
        }

        return true;
    }

    public function close(): void
    {
        $this->files = null;
    }

    public function read(?string $regex_filter = null): false|File
    {
        if (!is_array($this->files)) {
            if (!($files = $this->manager->scandir($this->path, $regex_filter, SCANDIR_SORT_NONE, $this->allow_hidden))) {
                return false;
            }
            $this->files = $files;
            reset($this->files);
        }
        if (($file = current($this->files)) === false) {
            return false;
        }
        next($this->files);

        return $file;
    }

    public function rewind(): void
    {
        if (!is_array($this->files)) {
            return;
        }
        reset($this->files);
    }

    /**
     * Find files in the current path optionally recursing into sub directories.
     *
     * @param string $pattern        The pattern to match against.  This can be either a wildcard string, such as
     *                               "*.txt" or a regex pattern.  Regex is detected if the string is longer than a
     *                               single character and first character is the same as the last.
     * @param bool   $case_sensitive if TRUE character case will be honoured
     * @param int    $depth          Recursion depth.  NULL will always recurse.  0 will prevent recursion.
     *
     * @return array<File> returns an array of matches files
     */
    public function find(string $pattern, bool $show_hidden = false, bool $case_sensitive = true, ?int $depth = null): ?array
    {
        $list = [];
        if (!($dir = $this->manager->scandir($this->path, null, SCANDIR_SORT_NONE, true, $this->relative_path))) {
            return null;
        }
        foreach ($dir as $item) {
            if (false === $show_hidden && '.' == substr($item->name(), 0, 1)) {
                continue;
            }
            if ($item->isDir() && (null === $depth || $depth > 0)) {
                if ($subdiritems = $item->find($pattern, $show_hidden, $case_sensitive, (null === $depth) ? $depth : $depth - 1)) {
                    $list = array_merge($list, $subdiritems);
                }
            } else {
                if (strlen($pattern) > 1 && substr($pattern, 0, 1) == substr($pattern, -1, 1)) {
                    if (0 == preg_match($pattern.($case_sensitive ? null : 'i'), (string) $item)) {
                        continue;
                    }
                } elseif (!fnmatch($pattern, $item->basename(), $case_sensitive ? 0 : FNM_CASEFOLD)) {
                    continue;
                }
                $list[] = $item;
            }
        }

        return $list;
    }

    public function copyTo(string $target, bool $recursive = false, null|callable|\Closure $transport_callback = null): bool
    {
        if ($this->manager->exists($target)) {
            if (!$this->manager->isDir($target)) {
                return false;
            }
        } elseif (!$this->manager->mkdir($target)) {
            return false;
        }
        $dir = $this->manager->scandir($this->path, null, SCANDIR_SORT_NONE, true);
        foreach ($dir as $cur) {
            if ('.' === $cur->name() || '..' === $cur->name()) {
                continue;
            }
            if ($transport_callback instanceof \Closure || is_callable($transport_callback)) {
                /*
                 * Call the transport callback.  If it returns true, do the copy.  False means do not copy this file.
                 * This gives the callback a chance to perform the copy itself in a special way, or ignore a
                 * file/directory
                 */
                if (!call_user_func_array($transport_callback, [$cur->fullpath(), $target.'/'.$cur->basename()])) {
                    continue;
                }
            }
            if ($cur->isDir()) {
                if ($recursive) {
                    $dir = new Dir($cur, $this->manager);
                    $dir->copyTo($target, $recursive, $transport_callback);
                }
            } else {
                $perms = $cur->perms();
                $new = $cur->copyTo($target);
                $new->chmod($perms);
            }
        }

        return true;
    }

    public function get(string $child, bool $force_dir = false): Dir|File
    {
        $path = $this->path($child);
        if (true === $force_dir) {
            return $this->getDir($child);
        }

        return new File($this->path($child), $this->manager, $this->relative_path ? $this->relative_path : $this->path);
    }

    public function getDir(string $path): Dir
    {
        return new Dir($this->path($path), $this->manager);
    }

    public function mimeContentType(): string
    {
        return 'httpd/unix-directory';
    }

    public function dir(?string $child = null): Dir
    {
        $relative_path = $this->relative_path ? $this->relative_path : $this->path;

        return new Dir($this->path($child), $this->manager, $relative_path);
    }

    /**
     * @return array<string>
     */
    public function toArray(): array
    {
        return $this->manager->toArray($this->path, SCANDIR_SORT_NONE, $this->allow_hidden);
    }

    /**
     * Copy a file object into the current directory.
     *
     * @param File $file The file to put in this directory
     */
    public function put(File $file, bool $overwrite = false): File
    {
        return $file->copyTo($this->path, $overwrite, false, $this->manager);
    }

    /**
     * Download a file from a URL directly to the directory and return a new File object.
     *
     * This is useful for download large files as this method will write the file directly
     * to storage.  Currently, only local storage is supported as this uses OS file access.
     *
     * @param mixed $source_url The source URL of the file to download
     * @param mixed $timeout    The download timeout after which an exception will be thrown
     *
     * @return File A file object for accessing the newly created file
     *
     * @throws \Exception
     */
    public function download($source_url, $timeout = 60)
    {
        $file = $this->get(basename($source_url));
        $resource = fopen(sys_get_temp_dir().'/'.basename($source_url), 'w+');
        $url = str_replace(' ', '%20', $source_url);
        if (function_exists('curl_version')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_FILE, $resource);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            if (!curl_exec($ch)) {
                throw new \Exception(curl_error($ch));
            }
            curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $options = [
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeout,
                    'follow_location' => 1,
                ],
            ];
            if (!($result = file_get_contents($url, false, stream_context_create($options)))) {
                throw new \Exception('Download failed.  Zero bytes received.');
            }
            $file->putContents($result);
        }

        return $file;
    }

    public function mediaURL(null|string|URL $set_path = null): ?URL
    {
        if (null !== $set_path) {
            if (!$set_path instanceof URL) {
                $set_path = new URL($set_path);
            }
            $this->__media_uri = $set_path;
        }
        if ($this->__media_uri) {
            return $this->__media_uri;
        }

        return $this->manager->URL($this->fullpath());
    }

    public function sync(
        Dir $source,
        bool $recursive = false,
        null|callable|\Closure $progress_callback = null,
        int $max_retries = 3
    ): bool {
        if (true !== $this->callSyncCallback($progress_callback, HZ_SYNC_DIR, ['src' => $source, 'dst' => $this])) {
            return false;
        }
        if (!$this->exists()) {
            $this->create();
        }
        while ($item = $source->read()) {
            $retries = $max_retries;
            for ($i = 0; $i < $retries; ++$i) {
                try {
                    $result = true;
                    if ($item->isDir()) {
                        if (false === $recursive) {
                            continue 2;
                        }
                        $item = $this->get($item->basename(), true);
                        if ($item instanceof Dir) {
                            $item->sync($item, $recursive, $progress_callback);
                        }
                    } elseif ($item instanceof File) {
                        $target = null;
                        if (true !== $this->callSyncCallback($progress_callback, HZ_SYNC_FILE, ['src' => $item, 'dst' => $this])) {
                            continue 2;
                        }
                        if (!($sync = (!$this->exists($item->basename())))) {
                            $target_file = $this->get($item->basename());
                            $sync = $item->mtime() > $target_file->mtime();
                        }
                        if ($sync && true === $this->callSyncCallback($progress_callback, HZ_SYNC_FILE_UPDATE, ['src' => $item, 'dst' => $this])) {
                            $target = $this->put($item, true);
                        }
                        $this->callSyncCallback($progress_callback, HZ_SYNC_FILE_COMPLETE, ['src' => $item, 'dst' => $this, 'target' => $target]);
                    }

                    continue 2;
                } catch (\Throwable $e) {
                    // If we get an exception, it could be due to a network issue, so notify any callbacks to handle the situation
                    if (is_callable($progress_callback)) {
                        // Check the result of the callback.  False will retry the file a maximumu of 3 times.  Anything else will continue.
                        if (true !== $this->callSyncCallback($progress_callback, HZ_SYNC_ERROR, ['src' => $source, 'dst' => $this, 'err' => $e])) {
                            continue 2;
                        }
                    } else {
                        // Otherwise maintain old behavior and hang back for sec to try again
                        sleep(1);
                    }
                }
            }

            throw isset($e) ? $e : new \Exception('Unknown error!');
        }
        $this->callSyncCallback($progress_callback, HZ_SYNC_DIR_COMPLETE, ['src' => $source, 'dst' => $this]);

        return true;
    }

    public function write(string $file, string $bytes, ?string $content_type = null): ?int
    {
        return $this->manager->write($this->manager->fixPath($this->path, $file), $bytes, $content_type);
    }

    private function callSyncCallback(): bool
    {
        $args = func_get_args();
        $callback = array_shift($args);
        if (!is_callable($callback)) {
            return true;
        }

        return false !== call_user_func_array($callback, $args) ? true : false;
    }
}
