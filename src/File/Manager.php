<?php

declare(strict_types=1);

namespace Hazaar\File;

use Hazaar\Application;
use Hazaar\Application\FilePath;
use Hazaar\File;
use Hazaar\File\Backend\Exception\Offline;
use Hazaar\File\Backend\Interfaces\Backend;
use Hazaar\HTTP\URL;
use Hazaar\Loader;

class Manager implements Backend
{
    /**
     * @var array<mixed> Default configuration for a media source
     */
    public static array $default_config = [
        'enabled' => true,
        'auth' => false,
        'allow' => [
            'read' => false,        // Default disallow reads when auth enabled
            'cmd' => false,        // Default disallow file manager commands
            'dir' => true,          // Allow directory listings
            'filebrowser' => false,  // Allow access to the JS file browser
        ],
        'userdef' => [],
        'failover' => false,
        'log' => false,
    ];
    public string $name;

    /**
     * @var array<string>
     */
    public static ?array $mime_types = null;

    /**
     * @var array<string, string>
     */
    private static array $backend_aliases = [
        'googledrive' => 'GoogleDrive',
        'sharepoint' => 'SharePoint',
        'webdav' => 'WebDAV',
    ];
    private static string $default_backend = 'local';

    /**
     * @var array<mixed>
     */
    private static array $default_backend_options = [];
    private Backend $backend;
    private string $backend_name;

    /**
     * @var array<mixed>
     */
    private array $options = [];
    private ?Manager $failover = null;
    private bool $in_failover = false;

    /**
     * Manager constructor.
     *
     * @param array<mixed> $backend_options
     */
    public function __construct(?string $backend = null, array $backend_options = [], ?string $name = null)
    {
        if (!$backend) {
            if (Manager::$default_backend) {
                $backend = Manager::$default_backend;
                $backend_options = Manager::$default_backend_options ? Manager::$default_backend_options : [];
            } else {
                $backend = 'local';
                $backend_options = ['root' => '/'];
            }
        }
        $class = 'Hazaar\File\Backend\\'.ake(self::$backend_aliases, $backend, ucfirst($backend));
        if (!class_exists($class)) {
            throw new Exception\BackendNotFound($backend);
        }
        $this->backend_name = $backend;
        $this->backend = new $class($backend_options, $this);
        if (!$this->backend instanceof Backend) {
            throw new Exception\InvalidBackend($backend);
        }
        if (!$name) {
            $name = strtolower($backend);
        }
        $this->name = $name;
    }

    public function __destruct()
    {
        try {
            if ($this->failover && false === $this->in_failover) {
                $this->failoverSync();
            }
        } catch (\Exception $e) {
            // Silently make no difference to the world around you
        }
    }

    /**
     * @return array<mixed>
     */
    public static function getAvailableBackends(): array|false
    {
        $backends = [];
        $backendPath = __DIR__.DIRECTORY_SEPARATOR.'Backend';
        if (!file_exists($backendPath)) {
            throw new \Exception('Backend path does not exist!');
        }
        $dir = dir($backendPath);
        while (($file = $dir->read()) !== false) {
            if ('.php' !== substr($file, -4) || '.' === substr($file, 0, 1) || '_' === substr($file, 0, 1)) {
                continue;
            }
            $source = ake(pathinfo($file), 'filename');
            $class = 'Hazaar\File\Backend\\'.$source;
            if (!class_exists($class)) {
                continue;
            }
            $backend = [
                'name' => strtolower($source),
                'label' => $class::label(),
                'class' => $class,
            ];
            $backends[] = $backend;
        }

        return $backends;
    }

    /**
     * Loads a Manager class by name as configured in media.json config.
     *
     * @param string        $name    The name of the media source to load
     * @param array <mixed> $options Extra options to override configured options
     */
    public static function select(string $name, ?array $options = null): false|Manager
    {
        $config = Application\Config::getInstance('media');
        if (!isset($config[$name])) {
            return false;
        }
        $source = array_merge_recursive(Manager::$default_config, $config->{$name});
        if (null !== $options) {
            $source['options']->extend($options);
        }
        $manager = new Manager($source['type'], $source['options'], $name);
        if (true === $source['failover'] || true === $config['global']['failover']) {
            $manager->activateFailover();
        }

        return $manager;
    }

    public function refresh(bool $reset = false): bool
    {
        return $this->backend->refresh($reset);
    }

    /**
     * @param array<mixed> $options
     */
    public static function configure(string $backend, array $options): void
    {
        Manager::$default_backend = $backend;
        Manager::$default_backend_options = $options;
    }

    public function activateFailover(): void
    {
        $this->failover = new Manager('local', [
            'root' => Application::getInstance()->getRuntimePath('media'.DIRECTORY_SEPARATOR.$this->name, true),
        ]);
    }

    public function failoverSync(): bool
    {
        if (!($this->failover && $this->backend->isDir('/'))) {
            return false;
        }
        $clean = ['dir' => [], 'file' => []];
        $names = $this->failover->find();
        foreach ($names as $name) {
            $item = $this->failover->get($name);
            if ($item->isDir()) {
                if ($this->backend->exists($name) || $this->backend->mkdir($name)) {
                    $clean['dir'][] = $name;
                }
            } elseif ($item instanceof File) {
                $this->backend->write($name, $item->getContents(), $item->mimeContentType(), true);
                $clean['file'][] = $name;
            }
        }
        foreach ($clean['file'] as $file) {
            $this->failover->unlink($file);
        }
        foreach ($clean['dir'] as $dir) {
            $this->failover->rmdir($dir);
        }

        return true;
    }

    public function getBackend(): Backend
    {
        return $this->backend;
    }

    public function getBackendName(): string
    {
        return $this->backend_name;
    }

    public function setOption(string $name, mixed $value): void
    {
        $this->options[$name] = $value;
    }

    public function getOption(string $name): mixed
    {
        return ake($this->options, $name);
    }

    public function fixPath(string $path, ?string $file = null): string
    {
        if (!$path) {
            return '';
        }
        if ('/' !== substr($path, 0, 1)) {
            $path = rtrim($this->getBackend()->cwd(), '/').'/'.$path;
        }
        $path = '/'.trim(str_replace('\\', '/', $path), '/');
        if ($file) {
            $path .= (('/' !== substr($path, -1, 1)) ? '/' : null).$file;
        }

        return $path;
    }

    /*
     * Authorisation Methods
     *
     * These are used by certain backends that require OAuth-like user authorisation
     */
    public function authorise(?string $redirect_uri = null): bool
    {
        $result = $this->backend->authorise($redirect_uri);
        if (false === $result) {
            header('Location: '.$this->backend->buildAuthUrl($redirect_uri));

            exit;
        }

        return $result;
    }

    /**
     * Alias to authorise() which is the CORRECT spelling.
     */
    public function authorize(?string $redirect_uri = null): mixed
    {
        return $this->authorise($redirect_uri);
    }

    public function authorised(): bool
    {
        return $this->backend->authorised();
    }

    public function authorized(): bool
    {
        return $this->authorised();
    }

    public function reset(): bool
    {
        if (!method_exists($this->backend, 'reset')) {
            return true;
        }

        return $this->backend->reset();
    }

    public function buildAuthURL(?string $callback_url = null): ?string
    {
        return $this->backend->buildAuthURL($callback_url);
    }

    /**
     * Return a file object for a given path.
     *
     * @param mixed $path The path to a file object
     *
     * @return Dir|File the File object
     */
    public function get($path)
    {
        $path = $this->fixPath($path);
        if ($this->backend->isDir($path)) {
            return new Dir($path, $this);
        }

        return new File($this->fixPath($path), $this);
    }

    /**
     * Return a directory object for a given path.
     *
     * @param mixed $path The path to a directory object
     *
     * @return Dir the directory object
     */
    public function dir($path = '/')
    {
        return new Dir($this->fixPath($path), $this);
    }

    /**
     * Return a directory object for a given path.
     *
     * @return array<string> the directory object
     */
    public function toArray(string $path, int $sort = SCANDIR_SORT_ASCENDING, bool $allow_hidden = false): array
    {
        return $this->backend->scandir($this->fixPath($path), null, $sort, $allow_hidden);
    }

    /**
     * Return a directory object for a given path.
     *
     * @return array<string> the directory object
     */
    public function find(?string $search = null, string $path = '/', bool $case_insensitive = false): array
    {
        $result = $this->backend->find($search, $path, $case_insensitive);
        if (false !== $result) {
            return $result;
        }
        $dir = $this->dir($path);
        $list = [];
        while (($file = $dir->read()) != false) {
            if ($file->isDir()) {
                $list[] = $file->fullpath();
                $list = array_merge($list, $this->find($search, $file->fullpath()));
            } else {
                if ($search) {
                    $first = substr($search, 0, 1);
                    if ((ctype_alnum($first) || '\\' == $first) == false
                        && $first == substr($search, -1, 1)) {
                        if (!preg_match($search.($case_insensitive ? 'i' : ''), $file->basename())) {
                            continue;
                        }
                    } elseif (!fnmatch($search, $file->basename(), $case_insensitive ? FNM_CASEFOLD : 0)) {
                        continue;
                    }
                }
                $list[] = $file->fullpath();
            }
        }

        return $list;
    }

    public function exists(string $path): bool
    {
        return $this->backend->exists($this->fixPath($path));
    }

    public function read(string $file, int $offset = -1, ?int $maxlen = null): false|string
    {
        $bytes = null;
        if ($this->failover && $this->failover->exists($file)) {
            $f = $this->failover->get($file); // Make the file as a directory to store logs
            $bytes = $f->getContents();
        } else {
            $bytes = $this->backend->read($this->fixPath($file), $offset, $maxlen);
        }

        return $bytes;
    }

    public function write(string $file, string $data, ?string $content_type = null, bool $overwrite = false): ?int
    {
        $result = false;

        try {
            $result = $this->backend->write($this->fixPath($file), $data, $content_type, $overwrite);
        } catch (Offline $e) {
            if (!$this->failover) {
                throw $e;
            }
            $this->in_failover = true;
            $f = $this->failover->get($file); // Make the file as a directory to store logs
            if ($f->isDir()) {
                throw new \Exception('File exists and is not a file!');
            }
            if (!$f->parent()->exists()) {
                $f->parent()->create(true);
            }
            $result = $f->putContents($data) > 0;
        }

        return $result;
    }

    /**
     * @param array<string,string> $file
     */
    public function upload(string $path, array $file, bool $overwrite = false): bool
    {
        return $this->backend->upload($this->fixPath($path), $file, $overwrite);
    }

    public function store(string $source, string $target): bool
    {
        $file = new File($source);
        if ('/' != substr(trim($target), -1, 1)) {
            $target .= '/';
        }

        return $this->backend->write($target.$file->name(), $file->getContents(), $file->mimeContentType()) > 0;
    }

    // File Operations
    public function copy(
        string $src,
        string $dst,
        bool $recursive = false,
        ?Manager $srcManager = null,
        ?\Closure $callback = null
    ): bool {
        if ($srcManager !== $this) {
            $file = new File($src, $srcManager);

            switch ($file->type()) {
                case 'file':
                    return $this->backend->write(
                        $this->fixPath($dst, $file->basename()),
                        $file->getContents(),
                        $file->mimeContentType()
                    ) > 0;

                case 'dir':
                    if (!$recursive) {
                        return false;
                    }

                    return $this->deepCopy($file->fullpath(), $dst, $srcManager, $callback);
            }

            throw new \Exception("Copy of source type '".$file->type()."' between different sources is currently not supported");
        }

        return $this->backend->copy($src, $dst, $recursive);
    }

    public function move(string $src, string $dst, ?Manager $srcManager = null): bool
    {
        if ($srcManager instanceof Manager && $srcManager->getBackend() !== $this->backend) {
            $file = $srcManager->get($src);

            switch ($file->type()) {
                case 'file':
                    $result = $this->backend->write($this->fixPath($dst, $file->basename()), $file->getContents(), $file->mimeContentType());
                    if ($result) {
                        return $srcManager->unlink($src);
                    }

                    return false;

                case 'dir':
                    $result = $this->deepCopy($file->fullpath(), $dst, $srcManager);
                    if ($result) {
                        return $srcManager->rmdir($src, true);
                    }

                    return false;
            }

            throw new \Exception("Move of source type '".$file->type()."' between different sources is currently not supported.");
        }

        return $this->backend->move($src, $dst);
    }

    public function mkdir(string $path): bool
    {
        return $this->backend->mkdir($this->fixPath($path));
    }

    public function rmdir(string $path, bool $recurse = false): bool
    {
        return $this->backend->rmdir($this->fixPath($path), $recurse);
    }

    public function unlink(string $path): bool
    {
        return $this->backend->unlink($this->fixPath($path));
    }

    public function isEmpty(string $path): bool
    {
        $files = $this->backend->scandir($this->fixPath($path));

        return 0 === count($files);
    }

    public function filesize(string $path): int
    {
        return $this->backend->filesize($this->fixPath($path));
    }

    // Advanced backend dependant features
    public function fsck(bool $skipRootReload = false): bool
    {
        return $this->backend->fsck($skipRootReload);
    }

    /**
     * @param array<string,int|string> $params
     */
    public function thumbnailURL(string $path, int $width = 100, int $height = 100, string $format = 'jpeg', array $params = []): false|string
    {
        return $this->backend->thumbnailURL($path, $width, $height, $format, $params);
    }

    public function link(string $src, string $dst): bool
    {
        return $this->backend->link($src, $dst);
    }

    public function getMeta(string $path, ?string $key = null): array|false|string
    {
        return $this->backend->getMeta($path, $key);
    }

    /**
     * @param array<mixed> $values
     */
    public function setMeta(string $path, array $values): bool
    {
        return $this->backend->setMeta($path, $values);
    }

    public function url(?string $path = null): URL
    {
        $appURL = new Application\URL('media', $this->name.($path ? '/'.ltrim($path, '/') : ''));

        return new URL($appURL->toString());
    }

    /**
     * @return array<Dir|File>|false
     */
    public function scandir(
        string $path,
        ?string $regex_filter = null,
        int $sort = SCANDIR_SORT_ASCENDING,
        bool $show_hidden = false,
        ?string $relative_path = null
    ): array|false {
        if (($items = $this->backend->scandir($this->fixPath($path))) === false) {
            return false;
        }
        if (!$relative_path) {
            $relative_path = rtrim($path, '/').'/';
        }
        foreach ($items as &$item) {
            $fullpath = $this->fixPath($path, $item);
            $item = ($this->isDir($fullpath) ? new Dir($fullpath, $this, $relative_path) : new File($fullpath, $this, $relative_path));
        }
        if ($this->failover && $this->failover->exists($path)) {
            $items = array_merge($items, $this->failover->scandir($path, $regex_filter, $sort, $show_hidden));
        }

        return $items;
    }

    public function touch(string $path): bool
    {
        return $this->backend->touch($this->fixPath($path));
    }

    public function realpath(string $path): ?string
    {
        return $this->backend->realpath($this->fixPath($path));
    }

    public function isReadable(string $path): bool
    {
        return $this->backend->isReadable($this->fixPath($path));
    }

    public function isWritable(string $path): bool
    {
        return $this->backend->isWritable($this->fixPath($path));
    }

    // TRUE if path is a directory
    public function isDir(string $path): bool
    {
        return $this->backend->isDir($this->fixPath($path));
    }

    // TRUE if path is a symlink
    public function isLink(string $path): bool
    {
        return $this->backend->isLink($this->fixPath($path));
    }

    // TRUE if path is a normal file
    public function isFile(string $path): bool
    {
        return $this->backend->isFile($this->fixPath($path));
    }

    // Returns the file type
    public function filetype(string $path): string
    {
        return $this->backend->filetype($this->fixPath($path));
    }

    // Returns the file create time
    public function filectime(string $path): int
    {
        return $this->backend->filectime($this->fixPath($path));
    }

    // Returns the file modification time
    public function filemtime(string $path): int
    {
        return $this->backend->filemtime($this->fixPath($path));
    }

    // Returns the file access time
    public function fileatime(string $path): int
    {
        return $this->backend->fileatime($this->fixPath($path));
    }

    public function fileperms(string $path): int
    {
        return $this->backend->fileperms($this->fixPath($path));
    }

    public function chmod(string $path, int $mode): bool
    {
        return $this->backend->chmod($this->fixPath($path), $mode);
    }

    public function chown(string $path, string $user): bool
    {
        return $this->backend->chown($this->fixPath($path), $user);
    }

    public function chgrp(string $path, string $group): bool
    {
        return $this->backend->chgrp($this->fixPath($path), $group);
    }

    public function cwd(): string
    {
        return $this->backend->cwd();
    }

    public function mimeContentType(string $path): ?string
    {
        $type = $this->backend->mimeContentType($this->fixPath($path));
        if (null !== $type) {
            return $type;
        }
        $info = pathinfo($path);
        if ($extension = strtolower(ake($info, 'extension'))) {
            return self::lookupContentType($extension);
        }

        return 'text/plain';
    }

    public function md5Checksum(string $path): ?string
    {
        return $this->backend->md5Checksum($this->fixPath($path));
    }

    /**
     * @param array<string,string> $params
     */
    public function previewURL(string $path, array $params = []): string
    {
        return $this->backend->previewURL($this->fixPath($path));
    }

    public function directURL(string $path): string
    {
        return $this->backend->directURL($this->fixPath($path));
    }

    public function openStream(string $path, string $mode): mixed
    {
        return $this->backend->openStream($path, $mode);
    }

    /**
     * @param resource $stream
     */
    public function writeStream($stream, string $bytes, ?int $length = null): int
    {
        return $this->backend->writeStream($stream, $bytes);
    }

    /**
     * @param resource $stream
     */
    public function readStream($stream, int $length): false|string
    {
        return $this->backend->readStream($stream, $length);
    }

    /**
     * @param resource $stream
     */
    public function seekStream(mixed $stream, int $offset, int $whence = SEEK_SET): int
    {
        return $this->backend->seekStream($stream, $offset, $whence);
    }

    /**
     * @param resource $stream
     */
    public function tellStream(mixed $stream): false|int
    {
        return $this->backend->tellStream($stream);
    }

    /**
     * @param resource $stream
     */
    public function eofStream(mixed $stream): bool
    {
        return $this->backend->eofStream($stream);
    }

    /**
     * @param resource $stream
     */
    public function truncateStream(mixed $stream, int $size): bool
    {
        return $this->backend->truncateStream($stream, $size);
    }

    /**
     * @param resource $stream
     */
    public function lockStream(mixed $stream, int $operation, ?int &$wouldblock = null): bool
    {
        return $this->backend->lockStream($stream, $operation, $wouldblock);
    }

    /**
     * @param resource $stream
     */
    public function flushStream(mixed $stream): bool
    {
        return $this->backend->flushStream($stream);
    }

    public function getsStream(mixed $stream, ?int $length = null): false|string
    {
        return $this->backend->getsStream($stream, $length);
    }

    /**
     * @param resource $stream
     */
    public function closeStream($stream): bool
    {
        return $this->backend->closeStream($stream);
    }

    public static function lookupContentType(string $extension): ?string
    {
        if (null === self::$mime_types) {
            self::$mime_types = [];
            $mt_file = Loader::getFilePath(FilePath::SUPPORT, 'mime.types');
            $h = fopen($mt_file, 'r');
            while ($line = fgets($h)) {
                $line = trim($line);
                if ('#' == substr($line, 0, 1) || 0 == strlen($line)) {
                    continue;
                }
                if (preg_match('/^(\S*)\s*(.*)$/', $line, $matches)) {
                    $extens = explode(' ', $matches[2]);
                    foreach ($extens as $value) {
                        if ($value) {
                            self::$mime_types[strtolower($value)] = $matches[1];
                        }
                    }
                }
            }
            fclose($h);
        }

        return ake(self::$mime_types, strtolower($extension));
    }

    private function deepCopy(string $src, string $dst, Manager $srcManager, ?\Closure $progressCallback = null): bool
    {
        $dstPath = rtrim($dst, '/').'/'.basename($src);
        if (!$this->exists($dstPath)) {
            $this->mkdir($dstPath);
        }
        $dir = new Dir($src, $srcManager);
        while (($f = $dir->read()) != false) {
            if ($progressCallback) {
                call_user_func_array($progressCallback, ['copy', $f]);
            }
            if ('dir' == $f->type()) {
                $this->deepCopy($f->fullpath(), $dstPath, $srcManager, $progressCallback);
            } else {
                $this->backend->write($this->fixPath($dstPath, $f->basename()), $f->getContents(), $f->mimeContentType());
            }
        }

        return true;
    }
}
