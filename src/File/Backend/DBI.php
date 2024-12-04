<?php

declare(strict_types=1);

namespace Hazaar\File\Backend;

use Hazaar\Date;
use Hazaar\DBI\Adapter;
use Hazaar\File\Manager;

class DBI implements Interfaces\Backend, Interfaces\Driver
{
    public string $separator = '/';
    protected Manager $manager;

    /**
     * @var array<mixed>
     */
    private array $options;
    private Adapter $db;

    /**
     * @var array<mixed>
     */
    private array $rootObject;

    /**
     * @param array<mixed> $options
     */
    public function __construct(array $options, Manager $manager)
    {
        $this->manager = $manager;
        $defaults = [
            'dbi' => Adapter::getDefaultConfig(),
            'initialise' => true,
            'chunkSize' => 4194304,
        ];
        $this->options = array_merge_recursive($defaults, $options);
        if (is_string($this->options['chunkSize'])) {
            $this->options['chunkSize'] = (int) bytes_str($this->options['chunkSize']);
        }
        $this->db = new Adapter($this->options['dbi']);
        $this->loadRootObject();
    }

    public static function label(): string
    {
        return 'Hazaar DBI Virtual Filesystem';
    }

    public function refresh(bool $reset = true): bool
    {
        return true;
    }

    public function loadRootObject(): bool
    {
        if (!($this->rootObject = $this->db->table('hz_file')->findOne(['parent' => null]))) {
            $this->rootObject = [
                'kind' => 'dir',
                'parent' => null,
                'filename' => 'ROOT',
                'created_on' => new Date(),
                'modified_on' => null,
                'length' => 0,
                'mime_type' => 'directory',
            ];
            if (!($this->rootObject['id'] = $this->db->table('hz_file')->insert($this->rootObject, 'id'))) {
                throw new \Exception('Unable to create DBI filesystem root object: '.$this->db->errorInfo()[2]);
            }
            /*
             * If we are recreating the ROOT document then everything is either
             *
             * a) New - In which case this won't do a thing
             *      - or possibly -
             * b) Screwed - In which case this should make everything work again.
             *
             */
            $this->fsck(true);
        }
        if (!$this->rootObject['created_on'] instanceof Date) {
            $this->rootObject['created_on'] = new Date($this->rootObject['created_on']);
        }
        if ($this->rootObject['modified_on'] && !$this->rootObject['modified_on'] instanceof Date) {
            $this->rootObject['modified_on'] = new Date($this->rootObject['modified_on']);
        }

        return is_array($this->rootObject);
    }

    public function fsck(bool $skip_root_reload = false): bool
    {
        $c = $this->db->table('hz_file')->find([], ['id', 'filename', 'parent']);
        while ($file = $c->fetch()) {
            if (!$file['parent']) {
                continue;
            }
            // Make sure an objects parent exist!
            if (!$this->db->table('hz_file')->exists(['id' => $file['parent']])) {
                $this->db->table('hz_file')->update(['id' => $file['id']], ['parent' => $this->rootObject['id']]);
            }
        }
        // Remove headless chunks
        $select = $this->db->table('hz_file_chunk', 'fc')
            ->leftjoin('hz_file', ['f.start_chunk' => ['$ref' => 'fc.id']], 'f')
            ->find(['f.id' => null, 'fc.parent' => null], ['fc.id'])
        ;
        while ($row = $select->fetch()) {
            $this->cleanChunk($row['id']);
        }
        if (true !== $skip_root_reload) {
            $this->loadRootObject();
        }
        // Check and de-dup any directories that have been duplicated accidentally
        $select = $this->db->table('hz_file')
            ->group(['parent', 'filename'])
            ->having(['count(*)' => ['$gt' => 1]])
            ->find(['kind' => 'dir'], ['parent', 'filename'])
        ;
        $count = 0;
        while (true) {
            $select->reset();
            $select->execute();
            if (0 === $select->count()) {
                break;
            }
            while ($row = $select->fetch()) {
                $this->dedupDirectory($row['parent'], $row['filename']);
            }
            if ($count++ > 16) {
                throw new \Exception('Maximum attempts to de-duplicate directories reached! (max=16)');
            }
        }
        // Check and de-dup any files
        $select = $this->db->table('hz_file')
            ->group(['parent', 'filename'])
            ->having(['count(*)' => ['$gt' => 1]])
            ->find([], ['parent', 'filename'])
        ;
        while ($row = $select->fetch()) {
            $copies = 0;
            // IMPORTANT: We sort by kind so that 'dir' is first.  This means it will end up being the master.
            $dups = $this->db->table('hz_file')->find(['parent' => $row['parent'], 'filename' => $row['filename']])
                ->sort(['kind' => 1, 'created_on' => 1])
                ->fetchAll()
            ;
            $master = array_shift($dups);
            foreach ($dups as $dup) {
                if ($dup['start_chunk'] === $master['start_chunk']) {
                    $this->db->table('hz_file')->delete(['id' => $dup['id']]);
                } else {
                    $this->db->table('hz_file')->update(['id' => $dup['id']], ['filename' => $dup['filename'].' (Copy #'.++$copies.')']);
                }
            }
        }
        $this->db->repair();

        return true;
    }

    /**
     * @return array<mixed>|bool
     */
    public function scandir(
        string $path,
        ?string $regex_filter = null,
        int $sort = SCANDIR_SORT_ASCENDING,
        bool $show_hidden = false,
        ?string $relative_path = null
    ): array|bool {
        if (!($parent = &$this->info($path))) {
            return false;
        }
        if (!array_key_exists('items', $parent)) {
            $this->loadObjects($parent);
        }
        $list = [];
        foreach ($parent['items'] as $file) {
            $fullpath = $path.$file['filename'];
            if ($regex_filter && !preg_match($regex_filter, $fullpath)) {
                continue;
            }
            $list[] = $file['filename'];
        }

        return $list;
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
        return true;
    }

    public function isWritable(string $path): bool
    {
        return true;
    }

    // true if path is a directory
    public function isDir(string $path): bool
    {
        if ($info = $this->info($path)) {
            return 'dir' == ake($info, 'kind');
        }

        return false;
    }

    // true if path is a symlink
    public function isLink(string $path): bool
    {
        return false;
    }

    // true if path is a normal file
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
            return strtotime(ake($info, 'created_on'));
        }

        return false;
    }

    // Returns the file modification time
    public function filemtime(string $path): false|int
    {
        if ($info = $this->info($path)) {
            return strtotime(ake($info, 'modified_on', $info['created_on'], true));
        }

        return false;
    }

    public function touch(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }
        $data = [
            'modified_on' => new Date(),
        ];
        if (!$this->db->table('hz_file')->update(['id' => $info['id']], $data)) {
            return false;
        }

        return true;
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

        return ake($info, 'length', 0);
    }

    public function fileperms(string $path): false|int
    {
        if (!($info = $this->info($path))) {
            return false;
        }

        return ake($info, 'mode');
    }

    public function mimeContentType(string $path): ?string
    {
        if ($info = $this->info($path)) {
            return ake($info, 'mime_type', false);
        }

        return null;
    }

    public function md5Checksum(string $path): ?string
    {
        if ($info = $this->info($path)) {
            return ake($info, 'md5');
        }

        return null;
    }

    /**
     * @param array<string,int|string> $params
     */
    public function thumbnailURL(string $path, int $width = 100, int $height = 100, string $format = 'jpeg', array $params = []): false|string
    {
        return false;
    }

    public function mkdir(string $path): bool
    {
        if ($info = $this->info($path)) {
            return false;
        }
        if (!($parent = &$this->info($this->dirname($path)))) {
            throw new \Exception('Unable to determine parent of path: '.$path);
        }
        $info = [
            'kind' => 'dir',
            'parent' => $parent['id'],
            'filename' => basename($path),
            'length' => 0,
            'created_on' => new Date(),
            'modified_on' => null,
        ];
        if (!($id = $this->db->table('hz_file')->insert($info, 'id')) > 0) {
            if (false === $id && '23505' === $this->db->errorCode()) { // Directory exists but not in memory so reload
                $this->loadObjects($parent);
            }

            return false;
        }
        $info['id'] = $id;
        if (!array_key_exists('items', $parent)) {
            $parent['items'] = [];
        }
        $parent['items'][$info['filename']] = $info;

        return true;
    }

    public function unlink(string $path): bool
    {
        if (!($info = $this->info($path))) {
            return false;
        }
        if (!($parent = &$this->info($this->dirname($path)))) {
            throw new \Exception('Unable to determine parent of path: '.$path);
        }
        if (!$this->db->table('hz_file')->delete(['id' => $info['id']])) {
            return false;
        }
        unset($parent['items'][$info['filename']]);
        if ('dir' !== $info['kind']) {
            $this->cleanChunk($info['start_chunk']);
        }

        return true;
    }

    public function rmdir(string $path, bool $recurse = false): bool
    {
        if ($info = $this->info($path)) {
            if ('dir' != $info['kind']) {
                return false;
            }
            $dir = $this->scandir($path, null, SCANDIR_SORT_ASCENDING, true);
            if (count($dir) > 0) {
                if ($recurse) {
                    foreach ($dir as $file) {
                        $fullPath = $path.$this->separator.$file;
                        if ($this->isDir($fullPath)) {
                            $this->rmdir($fullPath, true);
                        } else {
                            $this->unlink($fullPath);
                        }
                    }
                } else {
                    return false;
                }
            }
            if ($path == $this->separator) {
                return true;
            }

            return $this->unlink($path);
        }

        return false;
    }

    public function read(string $path, int $offset = -1, ?int $maxlen = null): false|string
    {
        if (!($item = $this->info($path))) {
            return false;
        }
        $sql = 'WITH RECURSIVE chunk_chain(id, parent, data) AS (SELECT id, parent, data FROM hz_file_chunk WHERE id = '.$item['start_chunk'];
        $sql .= ' UNION ALL SELECT fc.id, fc.parent, fc.data FROM chunk_chain cc INNER JOIN hz_file_chunk AS fc ON fc.parent = cc.id)';
        $sql .= ' SELECT data FROM chunk_chain;';
        $result = $this->db->query($sql);
        $bytes = '';
        while ($chunk = $result->fetch()) {
            $bytes .= stream_get_contents($chunk['data']);
        }

        return $bytes;
    }

    public function write(string $path, string $bytes, ?string $content_type = null, bool $overwrite = false): ?int
    {
        if (!($parent = &$this->info($this->dirname($path)))) {
            throw new \Exception('Unable to determine parent of path: '.$path);
        }
        $size = strlen($bytes);
        $md5 = md5($bytes);
        $chunk_id = null;

        if ($info = $this->db->table('hz_file')->findOne(['md5' => $md5])) {
            $chunk_id = $info['start_chunk'];
        } else {
            $chunk_size = $this->options['chunkSize'];
            $stmt = $this->db->prepare('INSERT INTO hz_file_chunk (parent, n, data) VALUES (?, ?, ?) RETURNING id;');
            $chunks = (int) ceil($size / $chunk_size);
            $last_chunk_id = null;
            for ($n = 0; $n < $chunks; ++$n) {
                $stmt->bindParam(1, $last_chunk_id);
                $stmt->bindParam(2, $n); // Support for multiple chunks will come later at some point
                if ($size > $chunk_size) {
                    $chunk = substr($bytes, $n * $chunk_size, $chunk_size);
                    $stmt->bindParam(3, $chunk, \PDO::PARAM_LOB);
                } else {
                    $stmt->bindParam(3, $bytes, \PDO::PARAM_LOB);
                }
                if (!($last_chunk_id = $stmt->execute()) > 0) {
                    throw $this->db->errorException('Write failed!');
                }
                settype($last_chunk_id, 'integer');
                if (0 === (int) $n) {
                    $chunk_id = $last_chunk_id;
                }
            }
        }
        if ($fileInfo = &$this->info($path)) {
            // If it's the same chunk, just bomb out because we are not updating anything
            if (($old_chunk = $fileInfo['start_chunk']) === $chunk_id) {
                return null;
            }
            $data = [
                'start_chunk' => $fileInfo['start_chunk'] = $chunk_id,
                'md5' => $fileInfo['md5'] = $md5,
                'modified_on' => $fileInfo['modified_on'] = new Date(),
                'length' => $size,
                'mime_type' => $content_type,
            ];
            if (!$this->db->table('hz_file')->update(['id' => $fileInfo['id']], $data)) {
                return null;
            }
            $this->cleanChunk($old_chunk);
        } else {
            $fileInfo = [
                'kind' => 'file',
                'parent' => $parent['id'],
                'start_chunk' => $chunk_id,
                'filename' => basename($path),
                'created_on' => new Date(),
                'modified_on' => new Date(),
                'length' => $size,
                'mime_type' => $content_type,
                'md5' => $md5,
            ];
            if (!($id = $this->db->table('hz_file')->insert($fileInfo, 'id'))) {
                throw $this->db->errorException();
            }
            $fileInfo['id'] = $id;
            if (!array_key_exists('items', $parent)) {
                $parent['items'] = [];
            }
            $parent['items'][$fileInfo['filename']] = $fileInfo;
        }

        return $size;
    }

    /**
     * Upload a file that was uploaded with a POST.
     *
     * @param array<string> $file
     */
    public function upload(string $path, array $file, bool $overwrite = false): bool
    {
        return $this->write(rtrim($path, $this->separator).$this->separator.$file['name'], file_get_contents($file['tmp_name']), $file['type'], $overwrite) > 0;
    }

    public function copy(string $src, string $dst, bool $overwrite = false): bool
    {
        if (!($source = $this->info($src))) {
            return false;
        }
        if (!($dstParent = &$this->info($this->dirname($dst)))) {
            throw new \Exception('Unable to determine parent of path: '.$dst);
        }
        if ('dir' !== $dstParent['kind']) {
            return false;
        }
        $target = $source;
        $target['filename'] = basename($dst);
        $target['modified_on'] = new Date();
        $target['parent'] = $dstParent['id'];
        unset($target['id']);
        if ($existing = &$this->info($dst)) {
            if (true !== $overwrite) {
                return false;
            }
            unset($dstParent['items'][$target['filename']]);
            if (!($id = $this->db->table('hz_file')->update(['id' => $existing['id']], $target))) {
                return false;
            }
        } else {
            if (!($id = $this->db->table('hz_file')->insert($target, 'id'))) {
                return false;
            }
            $target['id'] = $id;
        }

        if (!array_key_exists('items', $dstParent)) {
            $this->loadObjects($dstParent);
        } else {
            $dstParent['items'][$target['filename']] = $target;
        }

        return true;
    }

    public function link(string $src, string $dst): bool
    {
        if (!($source = $this->info($src))) {
            return false;
        }
        if (!($dstParent = &$this->info($this->dirname($dst)))) {
            throw new \Exception('Unable to determine parent of path: '.$dst);
        }
        if ($dstParent) {
            if ('dir' !== $dstParent['kind']) {
                return false;
            }
        } else {
            $dstParent = &$this->info($this->dirname($dst));
            if (false === $dstParent) {
                throw new \Exception('Unable to determine parent of path: '.$dst);
            }
        }
        if (!$dstParent) {
            return false;
        }
        $data = [
            'modified_on' => time(),
            'parent' => $dstParent['id'],
        ];
        if (!$this->db->table('hz_file')->update(['id' => $source['id']], $data)) {
            return false;
        }
        if (!array_key_exists('items', $dstParent)) {
            $dstParent['items'] = [];
        }
        $dstParent['items'][$source['filename']] = $source;

        return true;
    }

    public function move(string $src, string $dst, bool $overwrite = false): bool
    {
        if (substr($dst, 0, strlen($src)) == $src) {
            return false;
        }
        if (!($source = $this->info($src))) {
            return false;
        }
        if (!($srcParent = &$this->info($this->dirname($src)))) {
            throw new \Exception('Unable to determine parent of path: '.$src);
        }
        $data = [
            'modified_on' => new Date(),
        ];
        if (!($dstParent = &$this->info($this->dirname($dst)))) {
            throw new \Exception('Unable to determine parent of path: '.$dst);
        }
        if ($srcParent['id'] === $dstParent['id']) { // We are renaming the file.
            $data['filename'] = basename($dst);
            if (array_key_exists($data['filename'], $dstParent['items'])) {
                if (true !== $overwrite || 'file' !== $dstParent['items'][$data['filename']]['kind']) {
                    return false;
                }
                $this->db->table('hz_file')->delete(['id' => $dstParent['items'][$data['filename']]['id']]);
            }
            // Update the parents items array key with the new name.
            $basename = basename($src);
            $dstParent['items'][$data['filename']] = $dstParent['items'][$basename];
            unset($dstParent['items'][$basename]);
        } else {
            // If the destination exists and is NOT a directory, return false so we don't overwrite an existing file.
            if ('dir' !== $dstParent['kind']) {
                return false;
            }
            $data['parent'] = $dstParent['id'];
        }
        if (!($target = $this->db->table('hz_file')->update(['id' => $source['id']], $data, '*'))) {
            throw new \Exception($this->db->errorInfo()[2]);
        }
        $dstParent['items'][$data['filename']] = $target;

        return true;
    }

    public function chmod(string $path, int $mode): bool
    {
        if ($target = &$this->info($path)) {
            $target['mode'] = $mode;

            return $this->db->table('hz_file')->update(['id' => $target['id']], ['mode' => $mode]);
        }

        return false;
    }

    public function chown(string $path, string $user): bool
    {
        if ($target = &$this->info($path)) {
            $target['owner'] = $user;

            return false !== $this->db->table('hz_file')->update(['id' => $target['id']], ['owner' => $user]);
        }

        return false;
    }

    public function chgrp(string $path, string $group): bool
    {
        if ($target = &$this->info($path)) {
            $target['group'] = $group;

            return false !== $this->db->table('hz_file')->update(['id' => $target['id']], ['group' => $group]);
        }

        return false;
    }

    public function cwd(): string
    {
        return $this->separator;
    }

    public function setMeta(string $path, array $values): bool
    {
        if ($target = &$this->info($path)) {
            return false !== $this->db->table('hz_file')->update(['id' => $target['id']], ['metadata' => json_encode($values)]);
        }
        if ($parent = &$this->info($this->dirname($path))) {
            $parent['items'][basename($path)]['meta'] = $values;

            return true;
        }

        return false;
    }

    /**
     * @return array<string,mixed>|false
     */
    public function getMeta(string $path, ?string $key = null): array|false
    {
        if (!($info = $this->info($path))) {
            return false;
        }
        if (array_key_exists('metadata', $info)) {
            if ($key) {
                return ake($info['metadata'], $key);
            }

            return $info['metadata'];
        }

        return false;
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

    /**
     * @return array<string>|false
     */
    public function find(?string $search = null, string $path = '/', bool $case_insensitive = false): array|false
    {
        $list = [];
        $result = $this->db->table('hz_file')->find([[
            'filename' => [($case_insensitive ? '$ilike' : '$like') => '%'.$search.'%'],
        ]]);
        while ($file = $result->fetch()) {
            $list[] = $file['id'];
        }
        if (count($list) > 0) {
            return $this->resolveFullPaths($list);
        }

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

    /**
     * @param array<mixed> $parent
     *
     * @param-out array<mixed> $parent
     */
    private function loadObjects(?array &$parent = null): bool
    {
        $q = $this->db->table('hz_file')->find(['parent' => $parent['id']]);
        $parent['items'] = [];
        while ($object = $q->fetch()) {
            $parent['items'][$object['filename']] = $object;
        }

        return true;
    }

    private function dirname(string $path): string
    {
        if (($pos = strrpos($path, $this->separator)) !== false) {
            $path = substr($path, 0, (0 === $pos) ? $pos + 1 : $pos);
        }

        return $path;
    }

    /**
     * @return array<string,mixed>|false
     */
    private function &info(string $path): array|false
    {
        $parent = &$this->rootObject;
        if ($path === $this->separator) {
            return $parent;
        }
        $parts = explode($this->separator, $path);
        $false = false;
        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }
            if (!(array_key_exists('items', $parent) && is_array($parent['items']))) {
                $this->loadObjects($parent);
            }
            if (!array_key_exists($part, $parent['items'])) {
                return $false;
            }
            $parent = &$parent['items'][$part];
            if (!$parent) {
                return $false;
            }
        }

        return $parent;
    }

    private function dedupDirectory(string $parent, string $filename): bool
    {
        $q = $this->db->table('hz_file')->find(['parent' => $parent, 'filename' => $filename])->sort('created_on');
        $dups = $q->fetchAll();
        if (count($dups) < 2) {
            return true;
        }
        $master = array_shift($dups);
        foreach ($dups as $dup) {
            if ('dir' !== $dup['kind']) {
                continue;
            }
            $q = $this->db->table('hz_file')->find(['parent' => $dup['id']]);
            while ($child = $q->fetch()) {
                $this->db->table('hz_file')->update(['id' => $child['id']], ['parent' => $master['id']]);
            }
            $this->db->table('hz_file')->delete(['id' => $dup['id']]);
        }

        return true;
    }

    private function cleanChunk(int $start_chunk_id): bool
    {
        if (0 !== $this->db->table('hz_file')->find(['start_chunk' => $start_chunk_id])->count()) {
            return false;
        }
        $sql = 'WITH RECURSIVE chunk_chain(id, parent) AS (SELECT id, parent FROM hz_file_chunk WHERE id = '.$start_chunk_id;
        $sql .= ' UNION ALL SELECT fc.id, fc.parent FROM chunk_chain cc INNER JOIN hz_file_chunk AS fc ON fc.parent = cc.id)';
        $sql .= ' SELECT id FROM chunk_chain;';
        if (!($result = $this->db->query($sql))) {
            throw new \Exception($this->db->errorInfo()[2]);
        }

        return $this->db->table('hz_file_chunk')->delete(['id' => ['$in' => array_column($result->fetchAll(), 'id')]]);
    }

    /**
     * @param array<int> $arrayOfFileIDs
     *
     * @return array<string>|false
     */
    private function resolveFullPaths(array $arrayOfFileIDs): array|false
    {
        $sql = 'WITH RECURSIVE fullpath_cte (id, parent, fullpath) AS (
            -- Anchor member: select files with no parent
            SELECT f.id, f.parent, \'\' AS fullpath
            FROM hz_file f
            WHERE f.parent IS NULL
            UNION ALL
            -- Recursive member: build the path recursively
            SELECT f.id, f.parent, fp.fullpath || \'/\' || f.filename
            FROM hz_file f
            INNER JOIN fullpath_cte fp ON f.parent = fp.id
        )
        SELECT id, fullpath
        FROM fullpath_cte
        WHERE id IN ('.implode(', ', $arrayOfFileIDs).');';
        if ($result = $this->db->query($sql)) {
            return array_column($result->fetchAll(), 'fullpath');
        }

        return false;
    }
}
