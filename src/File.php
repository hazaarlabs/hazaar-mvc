<?php

declare(strict_types=1);

namespace Hazaar;

use Hazaar\Application\FilePath;
use Hazaar\Application\Runtime;
use Hazaar\File\Dir;
use Hazaar\File\Exception\TargetExists;
use Hazaar\File\Manager;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;
use Hazaar\HTTP\URL;
use Hazaar\Util\Arr;
use Hazaar\Util\Str;

define('FILE_FILTER_IN', 0);
define('FILE_FILTER_OUT', 1);
define('FILE_FILTER_SET', 2);

class File implements \JsonSerializable
{
    public string $sourceFile;
    // Encryption bits
    public static string $defaultCipher = 'aes-256-ctr';
    public static string $defaultKey = 'hazaar_secret_badass_key';
    protected Manager $manager;

    /**
     * @var array<mixed>
     */
    protected array $info;
    protected ?string $mimeContentType = null;

    /**
     * Any overridden file contents.
     *
     * This is normally used when performing operations on the file in memory, such as resizing an image.
     */
    protected string $contents = '';

    /**
     * @var ?array<string>
     */
    protected ?array $csvContents = null;

    /**
     * @var ?resource
     */
    protected mixed $resource = null;

    protected ?string $relativePath = null;
    private mixed $stream = null;
    private bool $encrypted = false;
    private ?URL $__media_url = null;

    /**
     * Content filters.
     *
     * @var array<mixed>
     */
    private array $filters = [];

    public function __construct(mixed $file = null, ?Manager $manager = null, ?string $relativePath = null)
    {
        if ($file instanceof File) {
            $manager = $file->manager;
            $this->info = $file->info;
            $this->mimeContentType = $file->mimeContentType;
            $file = $file->sourceFile;
        } elseif (is_resource($file)) {
            $meta = stream_get_meta_data($file);
            $this->resource = $file;
            $file = $meta['uri'];
        } else {
            if (empty($file)) {
                $file = Runtime::getInstance()->getPath('tmp', true).DIRECTORY_SEPARATOR.uniqid();
            }
        }
        if (!$manager instanceof Manager) {
            $manager = new Manager();
        }
        $this->manager = $manager;
        $this->sourceFile = $this->manager->fixPath($file);
        if ($relativePath) {
            $this->relativePath = rtrim($this->manager->fixPath($relativePath), '/');
        }
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function backend(): string
    {
        return $this->manager->getBackendName();
    }

    public function getManager(): Manager
    {
        return $this->manager;
    }

    public static function get(string $url, ?Client $client = null): bool|File
    {
        if (!preg_match('/^\w+\:\/\//', $url)) {
            return false;
        }
        $filename = basename($url);
        if (($pos = strpos($filename, '?')) !== false) {
            $filename = substr($filename, 0, $pos);
        }
        if (!$client) {
            $client = new Client();
        }
        $request = new Request($url, 'GET');
        $response = $client->send($request);
        if (200 !== $response->status) {
            return false;
        }
        if ($disposition = $response->getHeader('content-disposition')) {
            [$type, $rawParams] = explode(';', $disposition);
            $params = array_map(function ($value) {
                return trim($value ?? '', '"');
            }, Arr::unflatten(trim($rawParams)));
            if (isset($params['filename'])) {
                $filename = $params['filename'];
            }
        }
        $file = new File($filename);
        $file->setMimeContentType($response->getContentType());
        $file->setContents($response->body);

        return $file;
    }

    /**
     * Content filters.
     */
    public function registerFilter(int $type, \Closure|string $callable): bool
    {
        if (!array_key_exists($type, $this->filters)) {
            $this->filters[$type] = [];
        }
        if (is_string($callable)) {
            $callable = [$this, $callable];
        }
        if (!is_callable($callable)) {
            return false;
        }
        $this->filters[$type][] = $callable;

        return true;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setMeta(array $values): bool
    {
        return $this->manager->setMeta($this->sourceFile, $values);
    }

    public function getMeta(?string $key = null): mixed
    {
        return $this->manager->getMeta($this->sourceFile, $key);
    }

    // Basic output functions
    public function toString(): string
    {
        return $this->fullpath();
    }

    // Standard filesystem functions
    public function baseName(string $suffix = ''): string
    {
        return basename($this->sourceFile, $suffix);
    }

    public function dirName(): string
    {
        /*
         * Hack: The str_replace() call makes all directory separaters consistent as /.
         * The use of DIRECTORY_SEPARATOR should only be used in the local backend.
         */
        return str_replace('\\', '/', dirname($this->sourceFile));
    }

    public function name(): string
    {
        return pathinfo($this->sourceFile, PATHINFO_FILENAME);
    }

    public function fullPath(): string
    {
        $dir = $this->dirname();

        return rtrim($dir, '/').'/'.$this->basename();
    }

    /**
     * Get the relative path of the file.
     *
     * If the file was returned from a [[Hazaar\File\Dir]] object, then it will have a stored
     * relative path.  Otherwise any file/path can be provided in the form of another [[Hazaar\File]]
     * object, [[Hazaar\File\Dir]] object, or string path, and the relative path to the file will
     * be returned.
     *
     * @param mixed $path optional path to use as the relative path
     *
     * @return bool|string The relative path.  False when $path is not valid
     */
    public function relativePath($path = null)
    {
        if (null !== $path) {
            if ($path instanceof File) {
                $path = $path->dirname();
            }
            if ($path instanceof Dir) {
                $path = $path->fullpath();
            } elseif (!is_string($path)) {
                return false;
            }
            $sourcePath = explode('/', trim(str_replace('\\', '/', dirname($this->sourceFile)), '/'));
            $path = explode('/', trim(str_replace('\\', '/', $path), '/'));
            $index = 0;
            while (
                isset($sourcePath[$index], $path[$index])
                && $sourcePath[$index] === $path[$index]
            ) {
                ++$index;
            }
            $diff = count($sourcePath) - $index;

            return implode('/', array_merge(array_fill(0, $diff, '..'), array_slice($path, $index)));
        }
        if (!$this->relativePath) {
            return $this->fullpath();
        }
        $dirParts = explode('/', $this->dirname());
        $relParts = explode('/', $this->relativePath);
        $path = null;
        foreach ($dirParts as $index => $part) {
            if (array_key_exists($index, $relParts) && $relParts[$index] === $part) {
                continue;
            }
            $dirParts = array_slice($dirParts, $index);
            if (($count = count($relParts) - $index) > 0) {
                $dirParts = array_merge(array_fill(0, $count, '..'), $dirParts);
            }
            $path = implode('/', $dirParts);

            break;
        }

        return ($path ? $path.'/' : '').$this->basename();
    }

    public function setRelativePath(string $path): void
    {
        $this->relativePath = $path;
    }

    public function extension(): string
    {
        return pathinfo($this->sourceFile, PATHINFO_EXTENSION);
    }

    public function size(): ?int
    {
        if ($this->contents) {
            return strlen($this->contents);
        }
        if (!$this->exists()) {
            return null;
        }

        return $this->manager->filesize($this->sourceFile);
    }

    // Standard file modification functions
    public function exists(): bool
    {
        return $this->manager->exists($this->sourceFile);
    }

    public function realPath(): string
    {
        return $this->manager->realpath($this->sourceFile);
    }

    public function isReadable(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->isReadable($this->sourceFile);
    }

    public function isWritable(): bool
    {
        return $this->manager->isWritable($this->sourceFile);
    }

    public function isFile(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->isFile($this->sourceFile);
    }

    public function isDir(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->isDir($this->sourceFile);
    }

    public function isLink(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->isLink($this->sourceFile);
    }

    public function dir(): ?Dir
    {
        if ($this->isDir()) {
            return new Dir($this->sourceFile, $this->manager, $this->relativePath);
        }

        return $this->parent();
    }

    public function parent(): Dir
    {
        return new Dir($this->dirname(), $this->manager, $this->relativePath);
    }

    public function type(): ?string
    {
        if (!$this->exists()) {
            return null;
        }

        return $this->manager->filetype($this->sourceFile);
    }

    public function ctime(): ?int
    {
        if (!$this->exists()) {
            return null;
        }

        return $this->manager->filectime($this->sourceFile);
    }

    public function mtime(): ?int
    {
        if (!$this->exists()) {
            return null;
        }

        return $this->manager->filemtime($this->sourceFile);
    }

    public function touch(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->touch($this->sourceFile);
    }

    public function atime(): ?int
    {
        if (!$this->exists()) {
            return null;
        }

        return $this->manager->fileatime($this->sourceFile);
    }

    public function hasContents(): bool
    {
        if ($this->contents) {
            return true;
        }
        if (!$this->exists()) {
            return false;
        }

        return $this->manager->filesize($this->sourceFile) > 0;
    }

    /**
     * Returns the current contents of the file.
     */
    public function getContents(int $offset = -1, ?int $maxlen = null): string
    {
        if ($this->contents) {
            return $this->contents;
        }
        $this->contents = $this->manager->read($this->sourceFile, $offset, $maxlen);
        $this->filterIn($this->contents);

        return $this->contents;
    }

    /**
     * Put contents directly writes data to the storage backend without storing it in the file object itself.
     *
     * NOTE: This function is called internally to save data that has been updated in the file object.
     *
     * @param string $data      The data to write
     * @param bool   $overwrite Overwrite data if it exists
     */
    public function putContents(string $data, bool $overwrite = true): ?int
    {
        $contentType = $this->mimeContentType();
        if (!$contentType) {
            $contentType = 'text/text';
        }
        $this->filterOut($data);
        $this->contents = $data;

        return $this->manager->write($this->sourceFile, $data, $contentType, $overwrite);
    }

    /**
     * Sets the current contents of the file in memory.
     *
     * Calling this function does not directly update the content of the file "on disk".  To do that
     * you must call the File::save() method which will commit the data to storage.
     *
     * @param string $bytes The data to set as the content
     */
    public function setContents(?string $bytes): ?int
    {
        if (array_key_exists(FILE_FILTER_SET, $this->filters)) {
            foreach ($this->filters[FILE_FILTER_SET] as $filter) {
                call_user_func_array($filter, [&$bytes]);
            }
        }
        $this->contents = $bytes ?? '';

        return strlen($this->contents);
    }

    /**
     * Set the contents from an encoded string.
     *
     * Currently this supports only data URI encoded strings.  I have made this generic in case I come
     * across other types of encodings that will work with this method.
     */
    public function setDecodedContents(string $bytes): bool
    {
        if ('data:' == substr($bytes, 0, 5)) {
            $contentType = null;
            $encoding = null;
            // Check we have a correctly encoded data URI
            if (($pos = strpos($bytes, ',', 5)) === false) {
                return false;
            }
            $info = explode(';', $bytes);
            if (!(count($info) >= 2)) {
                return false;
            }
            [$header, $contentType] = explode(':', array_shift($info));
            if (!('data' === $header && $contentType)) {
                return false;
            }
            $content = array_pop($info);
            if (($pos = strpos($content, ',')) !== false) {
                $encoding = substr($content, 0, $pos);
                $content = substr($content, $pos + 1);
            }
            $this->contents = ('base64' == $encoding) ? base64_decode($content) : $content;
            $this->setMimeContentType($contentType);
            if (count($info) > 0) {
                $attributes = Arr::unflatten($info);
                if (array_key_exists('name', $attributes)) {
                    $this->sourceFile = $attributes['name'];
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Return the contents of the file as a data URI encoded string.
     *
     * This function is basically the opposite of Hazaar\File::set_decoded_contents() and will generate
     * a data URI based on the current MIME content type and the contents of the file.
     *
     * @return string
     */
    public function getEncodedContents()
    {
        $data = 'data:'.$this->mimeContentType().';';
        if ($this->sourceFile) {
            $data .= 'name='.basename($this->sourceFile).';';
        }
        $data .= 'base64,'.\base64_encode($this->getContents());

        return $data;
    }

    /**
     * Saves the current in-memory content to the storage backend.
     *
     * Internally this calls File::putContents() to write the data to the backend.
     */
    public function save(): ?int
    {
        return $this->putContents($this->getContents(), true);
    }

    /**
     * Saves this file objects content to another file name.
     *
     * @param string $filename  The filename to save as
     * @param bool   $overwrite Boolean flag to indicate that the destination should be overwritten if it exists
     */
    public function saveAs(string $filename, bool $overwrite = false): ?int
    {
        return $this->manager->write($filename, $this->getContents(), $this->mimeContentType(), $overwrite);
    }

    /**
     * Deletes the file from storage.
     */
    public function unlink(): bool
    {
        if (!$this->exists()) {
            return false;
        }
        if ($this->isDir()) {
            return $this->manager->rmdir($this->sourceFile, true);
        }

        return $this->manager->unlink($this->sourceFile);
    }

    /**
     * Generate an MD5 checksum of the current file content.
     */
    public function md5(): string
    {
        // Otherwise use the md5 provided by the backend.  This is because some backend providers (such as dropbox) provide
        // a cheap method of calculating the checksum
        if (($md5 = $this->manager->md5Checksum($this->sourceFile)) === null) {
            $md5 = md5($this->getContents());
        }

        return $md5;
    }

    /**
     * Return the base64 encoded content.
     */
    public function base64(): string
    {
        return base64_encode($this->getContents());
    }

    /**
     * Returns the contents as decoded JSON.
     *
     * If the content is a JSON encoded string, this will decode the string and return it as a stdClass
     * object, or an associative array if the $assoc parameter is TRUE.
     *
     * If the content can not be decoded because it is not a valid JSON string, this method will return FALSE.
     *
     * @param bool $assoc Return as an associative array.  Default is to use stdClass.
     *
     * @return array<mixed>|\stdClass
     */
    public function parseJSON(bool $assoc = false): array|false|\stdClass
    {
        $json = $this->getContents();
        $bom = pack('H*', 'EFBBBF');
        $json = preg_replace("/^{$bom}/", '', $json);
        if (false === json_validate($json)) {
            return false;
        }

        return json_decode($json, $assoc);
    }

    public function moveTo(string $destination, bool $overwrite = false, bool $createDest = false, ?Manager $dstManager = null): bool|File
    {
        $move = $this->exists();
        $file = $this->copyTo($destination, $overwrite, $createDest, $dstManager);
        if (!$file instanceof File) {
            return false;
        }
        if ($move) {
            $this->manager->unlink($this->sourceFile);
            $this->sourceFile = $destination.'/'.$this->basename();
            if ($dstManager) {
                $this->manager = $dstManager;
            }
        }

        return $file;
    }

    /**
     * Copy the file to another folder.
     *
     * This differs to copy() which expects the target to be the full new file pathname.
     *
     * @param string  $destination The destination folder to copy the file into
     * @param bool    $overwrite   overwrite the destination file if it exists
     * @param bool    $createDest  Flag that indicates if the destination folder should be created.  If the
     *                             destination does not exist an error will be thrown.
     * @param Manager $dstManager  The destination file manager.  Defaults to the same manager as the source.
     *
     * @throws \Exception
     * @throws File\Exception\SourceNotFound
     * @throws File\Exception\TargetNotFound
     */
    public function copyTo(
        string $destination,
        bool $overwrite = false,
        bool $createDest = false,
        ?Manager $dstManager = null
    ): bool|File {
        if (!$dstManager) {
            $dstManager = $this->manager;
        }
        if (!$this->exists()) {
            throw new File\Exception\SourceNotFound($this->sourceFile, $destination);
        }
        // If the destination is a Dir object, then we need to get the full path
        if ($dstManager->exists($destination)) {
            if (!$dstManager->isDir($destination) && true !== $overwrite) {
                throw new TargetExists($destination);
            }
            if (true !== $overwrite) {
                $destination = rtrim($destination, '/').'/'.$this->basename();
            }
        }
        if ($dstManager === $this->manager) {
            $result = $dstManager->copy($this->sourceFile, $destination, $overwrite, $this->manager);
        } else {
            $result = $dstManager->write($destination, $this->getContents(), $this->mimeContentType(), $overwrite);
        }
        if ($result) {
            return new File($destination, $dstManager, $this->relativePath);
        }

        return false;
    }

    /**
     * Copy the file to another folder and filename.
     *
     * This differs from copyTo which expects the target to be a folder
     *
     * @param string $destination The destination folder and file name to copy the file into
     * @param bool   $overwrite   overwrite the destination file if it exists
     * @param bool   $createDest  Flag that indicates if the destination folder should be created.  If the
     *                            destination does not exist an error will be thrown.
     * @param mixed  $dstManager  The destination file manager.  Defaults to the same manager as the source.
     *
     * @throws \Exception
     * @throws File\Exception\SourceNotFound
     * @throws File\Exception\TargetNotFound
     */
    public function copy($destination, $overwrite = false, $createDest = false, $dstManager = null): bool|File
    {
        if (!$dstManager) {
            $dstManager = $this->manager;
        }
        if ($this->contents) {
            $this->manager = $dstManager;
            if (!$dstManager->exists(dirname($destination))) {
                if (!$createDest) {
                    throw new \Exception('Destination does not exist!');
                }
                $dstManager->mkdir(dirname($destination), true);
            }
            $this->sourceFile = $destination;

            if (!$this->save()) {
                return false;
            }

            return $this;
        }
        if (!$this->exists()) {
            throw new File\Exception\SourceNotFound($this->sourceFile, $destination);
        }
        if (!$dstManager->exists(dirname($destination))) {
            if (!$createDest) {
                throw new \Exception('Destination does not exist!');
            }
            $parts = explode('/', dirname($destination));
            $dir = '';
            foreach ($parts as $part) {
                if ('' === $part) {
                    continue;
                }
                $dir .= '/'.$part;
                if (!$dstManager->exists($dir)) {
                    $dstManager->mkdir($dir);
                }
            }
        }
        if ($dstManager === $this->manager) {
            $result = $dstManager->copy($this->sourceFile, $destination, true, $this->manager);
        } else {
            $result = $dstManager->write($destination, $this->getContents(), true, $this->mimeContentType());
        }

        return new File($destination, $dstManager, $this->relativePath);
    }

    public function mimeContentType(): ?string
    {
        if (!$this->mimeContentType) {
            $this->mimeContentType = $this->manager->mimeContentType($this->fullpath());
        }

        return $this->mimeContentType;
    }

    public function setMimeContentType(string $type): void
    {
        $this->mimeContentType = $type;
    }

    /**
     * @param array<string,int|string> $params
     */
    public function thumbnailURL(int $width = 100, int $height = 100, string $format = 'jpeg', array $params = []): ?string
    {
        return $this->manager->thumbnailURL($this->fullpath(), $width, $height, $format, $params);
    }

    /**
     * @param array<string,string> $params
     */
    public function previewURL(array $params = []): ?string
    {
        return $this->manager->previewURL($this->fullpath(), $params);
    }

    public function directURL(): ?string
    {
        return $this->manager->directURL($this->fullpath());
    }

    public function mediaURL(null|string|URL $setPath = null): URL
    {
        if (null !== $setPath) {
            if (!$setPath instanceof URL) {
                $setPath = new URL($setPath);
            }
            $this->__media_url = $setPath;
        }
        if (null !== $this->__media_url) {
            return $this->__media_url;
        }

        return $this->manager->url($this->fullpath());
    }

    /**
     * @return array<string>
     */
    public function toArray(string $delimiter = "\n"): array
    {
        return explode($delimiter, $this->getContents());
    }

    /**
     * Return the CSV content as a parsed array.
     *
     * @param bool $useHeader_row Indicates if a header row should be parsed and used to build an associative array.  In this case the
     *                            keys in the returned array will be the values from the first row, which is normally a header row.
     *
     * @return array<array<string>>
     */
    public function readCSV(bool $useHeader_row = false): array
    {
        $data = array_map('str_getcsv', $this->toArray("\n"));
        if (true === $useHeader_row) {
            $headers = array_shift($data);
            foreach ($data as &$row) {
                if (count($headers) !== count($row)) {
                    continue;
                }
                $row = array_combine($headers, $row);
            }
        }

        return $data;
    }

    /**
     * Returns a line from the file pointer and parse for CSV fields.
     *
     * @param int $length Must be greater than the longest line (in characters) to be found in the CSV file
     *                    (allowing for trailing line-end characters). Otherwise the line is split in chunks
     *                    of length characters, unless the split would occur inside an enclosure.
     *
     *                          Omitting this parameter (or setting it to 0 in PHP 5.1.0 and later) the maximum
     *                          line length is not limited, which is slightly slower.
     * @param string $delimiter the optional delimiter parameter sets the field delimiter (one character only)
     * @param string $enclosure the optional enclosure parameter sets the field enclosure character (one character only)
     * @param string $escape    the optional escape parameter sets the escape character (one character only)
     *
     * @return array<string>
     */
    public function getCSV(int $length = 0, string $delimiter = ',', string $enclosure = '"', string $escape = '\\'): ?array
    {
        if (null === $this->csvContents) {
            $this->csvContents = explode("\n", $this->getContents());
            $line = reset($this->csvContents);
        } elseif (!($line = next($this->csvContents))) {
            return null;
        }

        return str_getcsv($line, $delimiter, $enclosure, $escape);
    }

    /**
     * Writes an array to the file in CSV format.
     *
     * @param array<int|string> $fields Must be greater than the longest line (in characters) to be found in the CSV file
     *                                  (allowing for trailing line-end characters). Otherwise the line is split in chunks
     *                                  of length characters, unless the split would occur inside an enclosure.
     *
     *                          Omitting this parameter (or setting it to 0 in PHP 5.1.0 and later) the maximum
     *                          line length is not limited, which is slightly slower.
     * @param string $delimiter the optional delimiter parameter sets the field delimiter (one character only)
     * @param string $enclosure the optional enclosure parameter sets the field enclosure character (one character only)
     * @param string $escape    the optional escape parameter sets the escape character (one character only)
     *
     * @return null|int
     */
    public function putCSV(array $fields, string $delimiter = ',', string $enclosure = '"', string $escape = '\\')
    {
        if (null === $this->csvContents) {
            $this->csvContents = [];
        }
        $this->csvContents[] = $line = Str::putCSV($fields, $delimiter, $enclosure, $escape);

        return strlen($line);
    }

    /**
     * Renames a file or directory.
     *
     * NOTE: This will not work if the file is currently opened by another process.
     *
     * @param string $newname The new name.  Must not be an absolute/relative path.  If you want to move the file use File::moveTo().
     */
    public function rename(string $newname): bool
    {
        if ('/' !== substr(trim($newname), 0, 1)) {
            $newname = $this->dirname().'/'.$newname;
        }
        if (true === $this->manager->move($this->sourceFile, $newname)) {
            $this->sourceFile = $newname;

            return true;
        }

        return false;
    }

    public static function delete(string $path): bool
    {
        $file = new File($path);

        return $file->unlink();
    }

    public function jsonSerialize(): mixed
    {
        return $this->getEncodedContents();
    }

    public function perms(): ?int
    {
        return $this->manager->fileperms($this->sourceFile);
    }

    public function chmod(int $mode): bool
    {
        return $this->manager->chmod($this->sourceFile, $mode);
    }

    /**
     * Check if a file is encrypted using the built-in Hazaar encryption method.
     */
    public function isEncrypted(): bool
    {
        if (!$this->exists()) {
            return false;
        }
        $stream = $this->manager->openStream($this->fullpath(), 'r');
        if (!is_resource($stream)) {
            throw new \Exception('File backend does not support direct read/write operations');
        }
        $bom = pack('H*', 'BADA55');  // Haha, Bad Ass!
        $encrypted = ($this->manager->readStream($stream, 3) == $bom);
        $this->manager->closeStream($stream);

        return $encrypted;
    }

    public function encrypt(): bool
    {
        $this->encrypted = true;

        return $this->save() > 0;
    }

    public function decrypt(): bool
    {
        $data = $this->getContents();
        $this->encrypted = false;

        return $this->putContents($data) > 0;
    }

    public function isOpen(): bool
    {
        return is_resource($this->stream);
    }

    public function open(string $mode): mixed
    {
        $this->stream = $this->manager->openStream($this->fullpath(), $mode);

        return $this->stream;
    }

    public function read(int $length): false|string
    {
        if (null === $this->stream) {
            return false;
        }

        return $this->manager->readStream($this->stream, $length);
    }

    public function write(string $bytes, int $length): false|int
    {
        if (null === $this->stream) {
            return false;
        }

        return $this->manager->writeStream($this->stream, $bytes, $length);
    }

    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        if (null === $this->stream) {
            return -1;
        }

        return $this->manager->seekStream($this->stream, $offset, $whence);
    }

    public function tell(): false|int
    {
        if (null === $this->stream) {
            return false;
        }

        return $this->manager->tellStream($this->stream);
    }

    public function eof(): bool
    {
        if (null === $this->stream) {
            return true;
        }

        return $this->manager->eofStream($this->stream);
    }

    public function truncate(int $size): bool
    {
        if (null === $this->stream) {
            return false;
        }

        return $this->manager->truncateStream($this->stream, $size);
    }

    public function lock(int $operation, ?int &$wouldblock = null): bool
    {
        if (null === $this->stream) {
            return false;
        }

        return $this->manager->lockStream($this->stream, $operation, $wouldblock);
    }

    public function flush(): bool
    {
        if (null === $this->stream) {
            return false;
        }

        return $this->manager->flushStream($this->stream);
    }

    public function gets(?int $length = null): false|string
    {
        if (null === $this->stream) {
            return false;
        }

        return $this->manager->getsStream($this->stream, $length);
    }

    public function close(): bool
    {
        if (null === $this->stream) {
            return false;
        }
        if ($this->manager->closeStream($this->stream)) {
            $this->stream = null;

            return true;
        }

        return false;
    }

    /**
     * Internal content filter.
     *
     * Checks if the content is modified in some way using a BOM mask.  Currently this is used to determine if a file
     * is encrypted and automatically decrypts it if an encryption key is available.
     */
    protected function filterIn(string &$content): bool
    {
        if (array_key_exists(FILE_FILTER_IN, $this->filters)) {
            foreach ($this->filters[FILE_FILTER_IN] as $filter) {
                call_user_func($filter, $content);
            }
        }
        $bom = substr($content, 0, 3);
        // Check if we are encrypted
        if ($bom === pack('H*', 'BADA55')) {  // Hazaar Encryption
            $this->encrypted = true;
            $cipherLen = openssl_cipher_iv_length(File::$defaultCipher);
            $iv = substr($content, 3, $cipherLen);
            $data = openssl_decrypt(substr($content, 3 + $cipherLen), File::$defaultCipher, $this->getEncryptionKey(), OPENSSL_RAW_DATA, $iv);
            if (false === $data) {
                throw new \Exception('Failed to decrypt file: '.$this->sourceFile.'. Bad key?');
            }
            $hash = substr($data, 0, 8);
            $content = substr($data, 8);
            if ($hash !== hash('crc32', $content)) {
                throw new \Exception('Failed to decrypt file: '.$this->sourceFile.'. Bad key?');
            }
        } elseif ($bom === pack('H*', 'EFBBBF')) {  // UTF-8
            $content = substr($content, 3);  // Just strip the BOM
        }

        return true;
    }

    /**
     * Internal content filter.
     *
     * Checks if the content is modified in some way using a BOM mask.  Currently this is used to determine if a file
     * is encrypted and automatically decrypts it if an encryption key is available.
     */
    protected function filterOut(string &$content): bool
    {
        if (array_key_exists(FILE_FILTER_OUT, $this->filters)) {
            foreach ($this->filters[FILE_FILTER_OUT] as $filter) {
                call_user_func_array($filter, [&$content]);
            }
        }
        if (true === $this->encrypted) {
            $bom = pack('H*', 'BADA55');
            if (substr($content, 0, 3) === $bom) {
                return false;
            }
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(File::$defaultCipher));
            $data = openssl_encrypt(hash('crc32', $content).$content, File::$defaultCipher, $this->getEncryptionKey(), OPENSSL_RAW_DATA, $iv);
            $content = $bom.$iv.$data;
        }

        return true;
    }

    private function getEncryptionKey(): string
    {
        if ($keyFile = Loader::getFilePath(FilePath::CONFIG, '.key')) {
            $key = trim(file_get_contents($keyFile));
        } else {
            $key = File::$defaultKey;
        }

        return md5($key);
    }
}
