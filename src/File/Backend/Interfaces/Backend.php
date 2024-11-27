<?php

declare(strict_types=1);

namespace Hazaar\File\Backend\Interfaces;

use Hazaar\File\Manager;
use Hazaar\Map;

interface Backend
{
    public function refresh(bool $reset = false): bool;

    /**
     * @return array<string>|bool
     */
    public function scandir(
        string $path,
        ?string $regex_filter = null,
        int $sort = SCANDIR_SORT_ASCENDING,
        bool $show_hidden = false,
        ?string $relative_path = null
    ): array|bool;

    public function touch(string $path): bool;

    // Check if file/path exists
    public function exists(string $path): bool;

    public function realpath(string $path): ?string;

    public function isReadable(string $path): bool;

    public function isWritable(string $path): bool;

    // TRUE if path is a directory
    public function isDir(string $path): bool;

    // TRUE if path is a symlink
    public function isLink(string $path): bool;

    // TRUE if path is a normal file
    public function isFile(string $path): bool;

    // Returns the file type
    public function filetype(string $path): false|string;

    // Returns the file create time
    public function filectime(string $path): false|int;

    // Returns the file modification time
    public function filemtime(string $path): false|int;

    // Returns the file access time
    public function fileatime(string $path): false|int;

    public function filesize(string $path): false|int;

    public function fileperms(string $path): false|int;

    public function chmod(string $path, int $mode): bool;

    public function chown(string $path, string $user): bool;

    public function chgrp(string $path, string $group): bool;

    public function unlink(string $path): bool;

    public function mimeContentType(string $path): ?string;

    public function md5Checksum(string $path): ?string;

    // Get the current working directory
    public function cwd(): string;

    // Create a directory
    public function mkdir(string $path): bool;

    public function rmdir(string $path, bool $recurse = false): bool;

    // Copy a file from src to dst
    public function copy(string $src, string $dst, bool $recursive = false): bool;

    // Move a file from src to dst
    public function move(string $src, string $dst): bool;

    // Create a link to a file
    public function link(string $src, string $dst): bool;

    // Read the contents of a file
    public function read(string $path, int $offset = -1, ?int $maxlen = null): false|string;

    // Write the contents of a file
    public function write(string $path, string $bytes, ?string $content_type = null, bool $overwrite = false): ?int;

    /**
     * @return array<string>|false
     */
    public function find(?string $search = null, string $path = '/', bool $case_insensitive = false): array|false;

    public function fsck(bool $skip_root_reload = false): bool;

    /**
     * Upload a file that was uploaded with a POST.
     *
     * @param array<string> $file
     */
    public function upload(string $path, array $file, bool $overwrite = false): bool;

    /**
     * @param array<mixed> $values
     */
    public function setMeta(string $path, array $values): bool;

    /**
     * @return array<mixed>|false|string
     */
    public function getMeta(string $path, ?string $key = null): array|false|string;

    /**
     * @param array<string,int|string> $params
     */
    public function previewURL(string $path, array $params = []): false|string;

    public function directURL(string $path): false|string;

    /**
     * @param array<string,int|string> $params
     */
    public function thumbnailURL(string $path, int $width = 100, int $height = 100, string $format = 'jpeg', array $params = []): false|string;

    public function authorise(?string $redirect_uri = null): bool;

    public function authorised(): bool;

    public function buildAuthURL(?string $callback_url = null): ?string;

    /**
     * Direct stream read/write methods.
     */
    public function openStream(string $path, string $mode): mixed;

    public function writeStream(mixed $stream, string $bytes, ?int $length = null): int;

    public function readStream(mixed $stream, int $length): false|string;

    public function seekStream(mixed $stream, int $offset, int $whence = SEEK_SET): int;

    public function tellStream(mixed $stream): false|int;

    public function eofStream(mixed $stream): bool;

    public function truncateStream(mixed $stream, int $size): bool;

    public function lockStream(mixed $stream, int $operation, ?int &$wouldblock = null): bool;

    public function flushStream(mixed $stream): bool;

    public function getsStream(mixed $stream, ?int $length = null): false|string;

    public function closeStream(mixed $stream): bool;
}

interface Manageable
{
    /**
     * @param array<mixed> $options
     */
    public function __construct(array|Map $options, Manager $manager);
}
