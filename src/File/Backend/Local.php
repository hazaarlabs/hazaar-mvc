<?php

declare(strict_types=1);

namespace Hazaar\File\Backend;

use Hazaar\File\BTree;
use Hazaar\File\Manager;

class Local implements Interfaces\Backend, Interfaces\Driver
{
    public string $separator = DIRECTORY_SEPARATOR;
    protected Manager $manager;

    /**
     * @var array<mixed>
     */
    private array $options;

    /**
     * @var array<mixed>
     */
    private array $meta = [];

    /**
     * @param array<mixed> $options
     */
    public function __construct(array $options, Manager $manager)
    {
        $this->manager = $manager;
        $this->options = array_merge_recursive(['display_hidden' => false, 'root' => DIRECTORY_SEPARATOR], $options);
    }

    public static function label(): string
    {
        return 'Local Filesystem Storage';
    }

    public function refresh(bool $reset = false): bool
    {
        return true;
    }

    public function resolvePath(string $path, ?string $file = null): string
    {
        $base = $this->options['root'] ?? DIRECTORY_SEPARATOR;
        if (DIRECTORY_SEPARATOR == $path) {
            $path = $base;
        } elseif (':' !== substr($path, 1, 1)) { // Not an absolute Windows path
            $path = $base.((DIRECTORY_SEPARATOR != substr($base, -1, 1)) ? DIRECTORY_SEPARATOR : null).trim($path, DIRECTORY_SEPARATOR);
        }
        if ($file) {
            $path .= ((strlen($path) > 1) ? DIRECTORY_SEPARATOR : null).trim($file, DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    /**
     * Get a directory listing.
     *
     * @return array<string>|bool
     */
    public function scandir(
        string $path,
        ?string $regex_filter = null,
        int $sort = SCANDIR_SORT_ASCENDING,
        bool $show_hidden = false,
        ?string $relative_path = null
    ): array|bool {
        $list = [];
        $path = $this->resolvePath($path);
        if (!is_dir($path)) {
            return false;
        }
        $dir = dir($path);
        while (($file = $dir->read()) != false) {
            if ('.metadata' == $file) {
                continue;
            }
            if ((false == $show_hidden && '.' == substr($file, 0, 1)) || '.' == $file || '..' == $file) {
                continue;
            }
            if ($regex_filter && !preg_match($regex_filter, $file)) {
                continue;
            }
            $list[] = $file;
        }

        if (SCANDIR_SORT_ASCENDING === $sort) {
            sort($list);
        } elseif (SCANDIR_SORT_DESCENDING === $sort) {
            rsort($list);
        }

        return $list;
    }

    public function read(string $file, int $offset = -1, ?int $maxlen = null): false|string
    {
        $file = $this->resolvePath($file);
        $ret = false;
        if (file_exists($file)) {
            if ($offset >= 0) {
                if ($maxlen) {
                    $ret = file_get_contents($file, false, null, $offset, $maxlen);
                } else {
                    $ret = file_get_contents($file, false, null, $offset);
                }
            } else {
                $ret = file_get_contents($file);
            }
        }

        return $ret;
    }

    public function write(string $file, string $data, ?string $content_type = null, bool $overwrite = true): ?int
    {
        $file = $this->resolvePath($file);
        if (file_exists($file) && false == $overwrite) {
            return null;
        }
        if (($ret = @file_put_contents($file, $data)) !== false) {
            return $ret;
        }

        return null;
    }

    /**
     * @param array<string> $file
     */
    public function upload(string $path, array $file, bool $overwrite = true): bool
    {
        $fullPath = $this->resolvePath(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file['name']);
        if (file_exists($fullPath) && false == $overwrite) {
            return false;
        }

        return move_uploaded_file($file['tmp_name'], $fullPath);
    }

    public function copy(string $src, string $dst, bool $recursive = false): bool
    {
        $src = rtrim($src, DIRECTORY_SEPARATOR);
        $dst = rtrim($dst, DIRECTORY_SEPARATOR);
        if ($this->isFile($src)) {
            $rSrc = $this->resolvePath($src);
            $rDst = $this->resolvePath($dst);
            if ($this->isDir($dst)) {
                $rDst = $this->resolvePath($dst, basename($src));
            }
            $ret = copy($rSrc, $rDst);
            if ($ret) {
                $this->meta[$rDst] = [$this->meta($rSrc), true];

                return true;
            }
        } elseif ($this->isDir($src) && $recursive) {
            $dst .= DIRECTORY_SEPARATOR.basename($src);
            if (!$this->exists($dst)) {
                $this->mkdir($dst);
            }
            $dir = $this->scandir($src);
            foreach ($dir as $file) {
                $fullpath = $src.DIRECTORY_SEPARATOR.$file;
                if ($this->isDir($fullpath)) {
                    $this->copy($fullpath, $dst, true);
                } else {
                    $this->copy($fullpath, $dst);
                }
            }

            return true;
        }

        return false;
    }

    public function link(string $src, string $dst): bool
    {
        $rSrc = $this->resolvePath($src);
        $rDst = $this->resolvePath($dst);
        if (file_exists($rDst)) {
            return false;
        }

        return link($rSrc, $rDst);
    }

    public function move(string $src, string $dst): bool
    {
        $rSrc = $this->resolvePath($src);
        $rDst = $this->resolvePath($dst);
        if (is_dir($rDst)) {
            $rDst = $this->resolvePath($dst, basename($src));
        }
        if (substr($dst, 0, strlen($src)) == $src) {
            return false;
        }
        $ret = rename($rSrc, $rDst);
        if ($ret) {
            $this->meta[$rDst] = [$this->meta($rSrc), true];
            unset($this->meta[$rSrc]);

            return true;
        }

        return false;
    }

    public function unlink(string $path): bool
    {
        $realPath = $this->resolvePath($path);
        if ((file_exists($realPath) || is_link($realPath)) && !is_dir($realPath)) {
            $ret = @unlink($realPath);
            if ($ret) {
                $metafile = dirname($realPath).DIRECTORY_SEPARATOR.'.metadata'.DIRECTORY_SEPARATOR.basename($realPath);
                if (file_exists($metafile)) {
                    unlink($metafile);
                }

                return true;
            }
        }

        return false;
    }

    public function mimeContentType(string $path): ?string
    {
        return null;
    }

    public function md5Checksum(string $path): ?string
    {
        if ($path = $this->resolvePath($path)) {
            return md5_file($path);
        }

        return null;
    }

    /**
     * Makes directory.
     */
    public function mkdir(string $path): bool
    {
        $path = $this->resolvePath($path);
        if (file_exists($path)) {
            return false;
        }
        if (!($result = @mkdir($path))) {
            throw new \Exception('Permission denied creating directory: '.$path);
        }

        return true;
    }

    /**
     * Removes directory.
     */
    public function rmdir(string $path, bool $recurse = false): bool
    {
        $realPath = $this->resolvePath($path);
        if (!is_dir($realPath)) {
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
        if (DIRECTORY_SEPARATOR == $path) {
            return true;
        }
        // Hack to get PHP on windows to let go of the now empty directory so that we can remove it
        $handle = opendir($realPath);
        closedir($handle);

        return rmdir($realPath);
    }

    /**
     * Checks whether a file or directory exists.
     */
    public function exists(string $path): bool
    {
        return file_exists($this->resolvePath($path));
    }

    /**
     * Returns canonicalized absolute pathname.
     */
    public function realpath(string $path): string
    {
        return realpath($this->resolvePath($path));
    }

    /**
     * true if path is a readable.
     */
    public function isReadable(string $path): bool
    {
        return is_readable($this->resolvePath($path));
    }

    /**
     * true if path is writable.
     */
    public function isWritable(string $path): bool
    {
        return is_writable($this->resolvePath($path));
    }

    /**
     * true if path is a directory.
     */
    public function isDir(string $path): bool
    {
        return is_dir($this->resolvePath($path));
    }

    /**
     * true if path is a symlink.
     */
    public function isLink(string $path): bool
    {
        return is_link($this->resolvePath($path));
    }

    /**
     * true if path is a normal file.
     */
    public function isFile(string $path): bool
    {
        return is_file($this->resolvePath($path));
    }

    /**
     * Returns the file type.
     */
    public function filetype(string $path): string
    {
        return filetype($this->resolvePath($path));
    }

    /**
     * Returns the file create time.
     */
    public function filectime(string $path): int
    {
        return filectime($this->resolvePath($path));
    }

    /**
     * Returns the file modification time.
     */
    public function filemtime(string $path): int
    {
        return filemtime($this->resolvePath($path));
    }

    /**
     * Sets access and modification time of file.
     */
    public function touch(string $path): bool
    {
        $path = $this->resolvePath($path);

        return touch($path);
    }

    // Returns the file access time
    public function fileatime(string $path): int
    {
        return fileatime($this->resolvePath($path));
    }

    public function filesize(string $path): int
    {
        return filesize($this->resolvePath($path));
    }

    public function fileperms(string $path): int
    {
        return fileperms($this->resolvePath($path));
    }

    public function chmod(string $path, int $mode): bool
    {
        return chmod($this->resolvePath($path), $mode);
    }

    public function chown(string $path, string $user): bool
    {
        return chown($this->resolvePath($path), $user);
    }

    public function chgrp(string $path, string $group): bool
    {
        return chgrp($this->resolvePath($path), $group);
    }

    public function cwd(): string
    {
        return getcwd();
    }

    /**
     * @param array<mixed> $values
     */
    public function setMeta(string $path, array $values): bool
    {
        $fullpath = $this->resolvePath($path);
        $db = $this->meta($fullpath);
        $meta = $db->get($fullpath);
        if (count($meta) > 0 && count($values) > 0) {
            $values = array_merge($meta, $values);
        }
        $db->set($fullpath, $values);

        return true;
    }

    /**
     * @return array<mixed>|string
     */
    public function getMeta(string $path, ?string $key = null): array|string
    {
        $fullpath = $this->resolvePath($path);
        $db = $this->meta($fullpath);
        $meta = $db->get($fullpath);
        if ($key) {
            return ake($meta, $key);
        }

        return $meta;
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

    public function authorise(?string $redirect_uri = null): bool
    {
        return true;
    }

    public function authorised(): bool
    {
        return true;
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
        $path = $this->resolvePath($path);
        if (false == $path) {
            return false;
        }

        return fopen($path, $mode);
    }

    /**
     * @param resource $stream
     */
    public function writeStream($stream, string $bytes, ?int $length = null): int
    {
        return fwrite($stream, $bytes, $length);
    }

    /**
     * @param resource $stream
     */
    public function readStream($stream, int $length): false|string
    {
        return fread($stream, $length);
    }

    /**
     * @param resource $stream
     */
    public function seekStream(mixed $stream, int $offset, int $whence = SEEK_SET): int
    {
        return fseek($stream, $offset, $whence);
    }

    /**
     * @param resource $stream
     */
    public function tellStream(mixed $stream): false|int
    {
        return ftell($stream);
    }

    /**
     * @param resource $stream
     */
    public function eofStream(mixed $stream): bool
    {
        return feof($stream);
    }

    /**
     * @param resource $stream
     */
    public function truncateStream(mixed $stream, int $size): bool
    {
        return ftruncate($stream, $size);
    }

    /**
     * @param resource $stream
     */
    public function lockStream(mixed $stream, int $operation, ?int &$wouldblock = null): bool
    {
        return flock($stream, $operation, $wouldblock);
    }

    /**
     * @param resource $stream
     */
    public function flushStream(mixed $stream): bool
    {
        return fflush($stream);
    }

    /**
     * @param resource $stream
     */
    public function getsStream(mixed $stream, ?int $length = null): false|string
    {
        return fgets($stream, $length);
    }

    /**
     * @param resource $stream
     */
    public function closeStream($stream): bool
    {
        return fclose($stream);
    }

    public function find(?string $search = null, string $path = '/', bool $case_insensitive = false): array|false
    {
        return false;
    }

    public function fsck(bool $skip_root_reload = false): bool
    {
        return false;
    }

    private function meta(string $fullpath): BTree
    {
        $metafile = dirname($fullpath).DIRECTORY_SEPARATOR.'.metadata';
        if (array_key_exists($metafile, $this->meta)) {
            return $this->meta[$metafile];
        }
        $this->meta[$metafile] = $db = new BTree($metafile);
        if (!($meta = $db->get($fullpath))) {
            $meta = [];
            // Generate Image Meta
            $content_type = $this->mimeContentType($fullpath);
            if (null !== $content_type && 'image' == substr($content_type, 0, 5)) {
                $size = getimagesize($fullpath);
                $meta['width'] = $size[0];
                $meta['height'] = $size[1];
                $meta['bits'] = ake($size, 'bits');
                $meta['channels'] = ake($size, 'channels');
            }
            $db->set($fullpath, $meta);
        }

        return $db;
    }
}
