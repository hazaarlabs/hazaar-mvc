<?php

declare(strict_types=1);

namespace Hazaar\File;

use Hazaar\File;
use Hazaar\Util\Arr;
use Hazaar\Util\Str;

/**
 * The file upload manager class.
 *
 * This class makes it much simpler to work with file uploaded via a POST request to the server.  Behind the scenes it
 * works with the PHP $_FILES global that contains information about any uploaded files.  This class then provides a few
 * simple methods to make saving all files, or a single file, much simpler.
 *
 * ### Examples
 *
 * Save all uploaded files to a directory:
 *
 * ```php
 * $upload = new \Hazaar\File\Upload();
 *
 * $upload->saveAll('/home/user', true);
 * ```
 *
 * Save a single file into a database if it has been uploaded:
 *
 * ```php
 * $upload = new \Hazaar\File\Upload();
 *
 * if($upload->has('my_new_file')){
 *
 *      $bytes = $upload->read('my_new_file');
 *
 *      $db->insert('file_contents', [
 *          'created' => 'Now()',
 *          'filename' => $upload->my_new_file['name'],
 *          'bytes' => $bytes
 *      ]);
 *
 * }
 * ```
 */
class Upload
{
    /**
     * The uploaded files array.
     *
     * @var array<mixed>
     */
    private array $files = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->files = $_FILES;
    }

    /**
     * Magic method to get details about an uploaded file.
     *
     * @param string $key the key of the uploaded file to return details of
     *
     * @return array<mixed>
     */
    public function __get(string $key): array
    {
        return $this->get($key);
    }

    /**
     * Check to see if there are any files that have uploaded as part of the current request.
     *
     * @param null|array<string>|string $opKeys A key, or array of keys to check for.  If any of the supplied keys do not exist then the
     *                                          method will return false.  If this parameter is not supplied this method will return true
     *                                          if ANY file has been uploaded.
     *
     * @return bool
     */
    public function uploaded(null|array|string $opKeys = null)
    {
        if ($opKeys && !is_array($opKeys)) {
            $opKeys = [$opKeys];
        }
        if ($opKeys) {
            foreach ($opKeys as $key) {
                if (!array_key_exists($key, $this->files) || !$this->files[$key]['tmp_name']) {
                    return false;
                }
            }

            return true;
        }
        if (count($this->files) > 0) {
            foreach ($this->files as $file) {
                if ($file['tmp_name']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return an array of uploaded file keys.
     *
     * The keys are the 'name' attribute for the form element that was used to upload the file.
     *
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->files);
    }

    /**
     * Test the existence of a file upload key.
     *
     * @param string $key The key to check for
     *
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->files);
    }

    /**
     * Get details about an uploaded file.
     *
     * @param string $key the key of the uploaded file to return details of
     *
     * @return array<mixed>
     */
    public function get(?string $key = null): ?array
    {
        if (null === $key) {
            $files = [];
            foreach ($this->keys() as $key) {
                $files[$key] = $this->get($key);
            }

            return $files;
        }
        if (($pos = strpos($key, '.')) > 0) {
            $subKey = substr($key, $pos + 1);
            $files = Arr::toDotNotation($this->get(substr($key, 0, $pos)), '.', substr_count($subKey, '.') + 1);

            return $files[$subKey] ?? [];
        }
        if (!($info = $this->files[$key] ?? null)) {
            return null;
        }
        if (!is_array($info['name'])) {
            return $info;
        }
        $files = [];
        foreach ($info as $item => $itemInfo) {
            foreach (Arr::toDotNotation($itemInfo) as $name => $data) {
                $files[$name.'.'.$item] = $data;
            }
        }

        return Arr::fromDotNotation($files);
    }

    /**
     * Save all the files that were uploaded to a single directory.
     *
     * This will iterate through all uploaded files and save them to the specified
     * destination directory.  If a callback function is specified then that function
     * will be executed for each file.  Arguments to the function are the key, $name,
     * $size and $type.  If the callback function returns false,  the file is NOT copied.
     * The $name argument is also checked after the function call to give the callback
     * function a chance to alter the destination filename (see example).
     *
     * ```php
     * $files->saveAll('/home/user', false, function($key, &$name, $size, $type)){
     *
     *      if($type == 'image/jpeg'){
     *
     *          $name = uniqid() . '.jpeg';
     *
     *          return true;
     *
     *      }
     *
     *      return false;
     * });
     * ```
     *
     * @param string   $destination The destination directory into which we copy the files
     * @param \Closure $callback    A callback function that will be called for each file.  This function must return
     *                              true for the file to be copied. The $name field is passed byRef so the file can be
     *                              renamed on the way through.
     */
    public function saveAll(string $destination, bool $overwrite = false, ?\Closure $callback = null): void
    {
        foreach ($this->keys() as $key) {
            $files = $this->getFile($key);
            if (!is_array($files)) {
                $files = [$files];
            }
            foreach ($files as $file) {
                $save = true;
                if ($callback instanceof \Closure) {
                    $save = $callback($key, $file);
                }
                if (true === $save) {
                    $file->copyTo($destination, $overwrite);
                }
            }
        }
    }

    /**
     * Save an uploaded file to a destination.
     *
     * @param string $key         The index key of the file to save
     * @param string $destination The destination file or directory to save the file to
     * @param bool   $overwrite   Flag to indicate if existing files should be overwritten
     *
     * @return bool
     */
    public function save($key, $destination, $overwrite = false)
    {
        if (!($files = $this->getFile($key))) {
            return false;
        }
        if (!is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $file) {
            $file->copyTo($destination, $overwrite);
        }

        return true;
    }

    /**
     * Read the contents of an uploaded file and return the bytes.
     *
     * @param string $key The key name of the file to return.  Use \Hazaar\Upload\File::keys() to get this.
     *
     * @return string the bytes for the uploaded file
     */
    public function read($key): ?string
    {
        if (array_key_exists($key, $this->files)) {
            $file = $this->files[$key];
            if (file_exists($file['tmp_name'])) {
                return file_get_contents($file['tmp_name']);
            }
        }

        return null;
    }

    /**
     * Returns the uploaded file as a Hazaar\File object.
     *
     * @return array<File>|File
     */
    public function getFile(?string $key = null): array|false|File
    {
        if (!($file = $this->get($key))) {
            return false;
        }

        return $this->resolveFiles($file);
    }

    public static function getMaxUploadSize(): int
    {
        $max_size = -1;
        if (($post_max_size = Str::toBytes(ini_get('post_max_size'))) > 0) {
            $max_size = $post_max_size;
        }
        $upload_max_filesize = Str::toBytes(ini_get('upload_max_filesize'));
        if ($upload_max_filesize > 0 && $upload_max_filesize < $max_size) {
            $max_size = $upload_max_filesize;
        }

        return $max_size;
    }

    /**
     * @param array<string,mixed> $array
     *
     * @return array<File>|File
     */
    private function resolveFiles(array $array): array|File
    {
        if (array_key_exists('tmp_name', $array)) {
            if ($array['error'] > 0) {
                throw new \Exception('Upload error processing '.$array['name']);
            }
            $file = new File($array['name']);
            $file->setMimeContentType($array['type']);
            $file->setContents(file_get_contents($array['tmp_name']));

            return $file;
        }
        $files = [];
        foreach ($array as $key => $info) {
            $files[$key] = $this->resolveFiles($info);
        }

        return $files;
    }
}
