<?php

declare(strict_types=1);

namespace Hazaar\File;

use Hazaar\File;

class GZFile extends File
{
    private int $level = -1;
    private int $encoding = FORCE_GZIP;

    /**
     * @var null|resource
     */
    private mixed $handle = null;

    public function __construct(mixed $file = null, ?Manager $manager = null)
    {
        parent::__construct($file, $manager);
        $this->setMimeContentType('application/gzip');
    }

    /**
     * Sets the level of compression.
     *
     * Can be given as 0 for no compression up to 9 for maximum compression.
     *
     * If -1 is used, the default compression of the zlib library is used which is 6.
     */
    public function setCompressionLevel(int $level): void
    {
        $this->level = (int) $level;
    }

    /**
     * Returns the current compression level setting.
     *
     * Can be a value of 0 for no compression up to 9 for maximum compression.
     *
     * If the value is -1, the default compression of the zlib library is being used, which is 6.
     */
    public function getCompressionLevel(): int
    {
        return $this->level;
    }

    /**
     * Set the current encoding for compress.
     */
    public function setEncoding(int $encoding): bool
    {
        if (!(FORCE_GZIP === $encoding || FORCE_DEFLATE === $encoding)) {
            return false;
        }
        $this->encoding = $encoding;

        return true;
    }

    /**
     * Returns the current gzencode encoding.
     */
    public function getEncoding(): int
    {
        return $this->encoding;
    }

    /**
     * Open gz-file.
     */
    public function open(string $mode = 'r'): mixed
    {
        if ($this->handle) {
            return $this->handle;
        }

        return $this->handle = gzopen($this->source_file, $mode);
    }

    /**
     * Close an open gz-file pointer.
     */
    public function close(): bool
    {
        if (null === $this->handle) {
            return false;
        }
        gzclose($this->handle);
        $this->handle = null;

        return true;
    }

    /**
     * Binary-safe gz-file read.
     *
     * File::read() reads up to length bytes from the file pointer referenced by handle. Reading stops as soon as one of the following conditions is met:
     *
     * * length bytes have been read
     * * EOF (end of file) is reached
     * * a packet becomes available or the socket timeout occurs (for network streams)
     * * if the stream is read buffered and it does not represent a plain file, at most one read of up to a number
     *   of bytes equal to the chunk size (usually 8192) is made; depending on the previously buffered data, the
     *   size of the returned data may be larger than the chunk size.
     *
     * @param int $length up to length number of bytes read
     */
    public function read(int $length): false|string
    {
        if (null === $this->handle) {
            return false;
        }

        return gzread($this->handle, $length);
    }

    /**
     * Binary-safe gz-file write.
     *
     * File::write() writes the contents of string to the file stream pointed to by handle.
     *
     * @param string $string the string that is to be written
     * @param int    $length If the length argument is given, writing will stop after length bytes have been written or the end
     *                       of string is reached, whichever comes first.
     *
     *                      Note that if the length argument is given, then the magic_quotes_runtime configuration option
     *                      will be ignored and no slashes will be stripped from string.
     */
    public function write(string $string, ?int $length = null): false|int
    {
        if (null === $this->handle) {
            return false;
        }
        if (null === $length) {
            return gzwrite($this->handle, $string);
        }

        return gzwrite($this->handle, $string, $length);
    }

    /**
     * Returns a character from the file pointer.
     */
    public function getc(): false|string
    {
        if (null === $this->handle) {
            return false;
        }

        return gzgetc($this->handle);
    }

    /**
     * Returns a line from the file pointer.
     */
    public function gets(?int $length = null): false|string
    {
        if (null === $this->handle) {
            return false;
        }

        return gzgets($this->handle, $length);
    }

    /**
     * Returns a line from the file pointer and strips HTML tags.
     *
     * @param array<string>|string $allowable_tags
     */
    public function getss(null|array|string $allowable_tags = null): false|string
    {
        if (null === $this->handle) {
            return false;
        }

        return strip_tags(gzgets($this->handle), $allowable_tags);
    }

    /**
     * Seeks to a position in the file.
     *
     * @param int $offset The offset. To move to a position before the end-of-file, you need to pass a negative value in offset and set whence to SEEK_END.
     * @param int $whence whence values are:
     *                    SEEK_SET - Set position equal to offset bytes.
     *                    SEEK_CUR - Set position to current location plus offset.
     *                    SEEK_END - Set position to end-of-file plus offset.
     */
    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        if (null === $this->handle) {
            return -1;
        }

        return gzseek($this->handle, $offset, $whence);
    }

    /**
     * Returns the current position of the file read/write pointer.
     */
    public function tell(): false|int
    {
        if (null === $this->handle) {
            return false;
        }

        return gztell($this->handle);
    }

    /**
     * Rewind the position of a file pointer.
     *
     * Sets the file position indicator for handle to the beginning of the file stream.
     */
    public function rewind(): bool
    {
        if (null === $this->handle) {
            return false;
        }

        return gzrewind($this->handle);
    }

    /**
     * Tests for end-of-file on a file pointer.
     *
     * @return bool TRUE if the file pointer is at EOF or an error occurs; otherwise returns FALSE
     */
    public function eof(): bool
    {
        if (null === $this->handle) {
            return false;
        }

        return gzeof($this->handle);
    }

    /**
     * Returns the current contents of the file.
     *
     * @param mixed $offset
     * @param mixed $maxlen
     *
     * @return mixed
     */
    public function get_contents($offset = -1, $maxlen = null)
    {
        if ($this->contents) {
            return $this->contents;
        }
        $this->contents = gzdecode($this->manager->read($this->source_file, $offset, $maxlen));
        $this->filterIN($this->contents);

        return $this->contents;
    }

    /**
     * Put contents directly writes data to the storage manager without storing it in the file object itself.
     *
     * NOTE: This function is called internally to save data that has been updated in the file object.
     */
    public function putContents(string $data, bool $overwrite = true): ?int
    {
        return parent::putContents(gzencode($data, $this->level, $this->encoding), $overwrite);
    }

    /**
     * Saves this file objects content to another file name.
     *
     * @param string $filename  The filename to save as
     * @param bool   $overwrite Boolean flag to indicate that the destination should be overwritten if it exists
     */
    public function saveAs(string $filename, bool $overwrite = false): ?int
    {
        return $this->manager->write($filename, gzencode($this->contents, $this->level, $this->encoding), $this->mimeContentType(), $overwrite);
    }
}
