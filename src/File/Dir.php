<?php

namespace Hazaar\File;

class Dir {

    private $path;

    private $backend;

    private $files;

    private $allow_hidden = FALSE;

    function __construct($path, $backend = NULL) {

        if(! $backend)
            $backend = new Backend\Local(array('root' => '/'));

        $this->path = $this->fixPath($path);

        $this->backend = $backend;

    }

    public function fixPath($path, $file = NULL) {

        if($file)
            $path .= ((strlen($path) > 1) ? '/' : NULL) . $file;

        return $path;

    }

    public function path($suffix = NULL) {

        return $this->fixPath($this->path, $suffix);

    }

    public function realpath($suffix = NULL) {

        return $this->backend->realpath($this->fixPath($this->path, $suffix));

    }

    public function exists() {

        return $this->backend->exists($this->path);

    }

    public function is_readable() {

        return $this->backend->is_readable($this->path);

    }

    public function is_writable() {

        return $this->backend->is_writable($this->path);

    }

    public function allow_hidden($toggle = TRUE) {

        $this->allow_hidden = $toggle;

    }

    public function create($recursive = FALSE) {

        if(! $recursive)
            return $this->backend->mkdir($this->path);

        $parents = array();

        $last = $this->path;

        while(! ($exists = $this->backend->exists($last))) {

            $parents[] = $last;

            $last = dirname($last);

            if($last == '/')
                break;

        }

        while($parent = array_pop($parents)) {

            if(! $this->backend->mkdir($parent))
                return FALSE;

        }

        return TRUE;

    }

    public function delete($recursive = FALSE) {

        if($recursive) {

            while($file = $this->read())
                $file->unlink();

        }

        return $this->backend->rmdir($this->path);

    }

    public function close() {

        $this->files = NULL;

    }

    public function read($regex_filter = NULL) {

        if(! is_array($this->files)) {

            $this->files = $this->backend->scandir($this->path, $regex_filter, $this->allow_hidden);

            if(($file = $this->rewind()) == FALSE)
                return FALSE;

        } else {

            if(($file = next($this->files)) === FALSE)
                return FALSE;

        }

        return new \Hazaar\File($this->fixPath($this->path, $file), $this->backend);

    }

    public function rewind() {

        if(! is_array($this->files))
            return FALSE;

        return reset($this->files);

    }

    /**
     * Find files in the current path optionally recursing into sub directories.
     *
     * @param string $pattern The pattern to match against.  This can be either a wildcard string, such as
     *                                  "*.txt" or a regex pattern.  Regex is detected if the string is longer than a
     *                                  single character and first character is the same as the last.
     * @param bool $recursive If TRUE the search will recurse into sub directories.
     * @param bool $case_sensitive If TRUE character case will be honoured.
     * @param string $start String path to start at if the search should start at a sub directory.
     * @param bool $relative Return a path relative to the search path, default is to return absolute paths.
     * @return array    Returns an array of matches files.
     */
    public function find($pattern, $show_hidden = FALSE, $case_sensitive = TRUE, $start = NULL) {

        if(! $start)
            $start = $this->path;

        $start = rtrim($start, '/') . '/';

        $list = array();

        $dir = $this->backend->scandir($start, NULL, TRUE);

        foreach($dir as $file) {

            if(($show_hidden === FALSE && substr($file, 0, 1) == '.') || $file == '.' || $file == '..')
                continue;

            if(is_dir($start . $file)) {

                $subdir = $this->find($pattern, $show_hidden, $case_sensitive, $start . $file);

                $list = array_merge($list, $subdir);

            }

            if(strlen($pattern) > 1 && substr($pattern, 0, 1) == substr($pattern, -1, 1)) {

                if(preg_match($pattern . ($case_sensitive ? NULL : 'i'), $file) == 0)
                    continue;

            } elseif(! fnmatch($pattern, $file))
                continue;

            $list[] = $start . $file;

        }

        return $list;

    }

    public function copyTo($target, $recursive = FALSE, $transport_callback = NULL) {

        $target = $this->fixPath($target);

        if($this->backend->exists($target)) {

            if(! $this->backend->is_dir($target))
                return FALSE;

        } else if(! $this->backend->mkdir($target))
            return FALSE;

        $dir = $this->backend->scandir($this->path, NULL, TRUE);

        foreach($dir as $cur) {

            if($cur == '.' || $cur == '..')
                continue;

            $sourcePath = $this->fixPath($this->path, $cur);

            $targetPath = $this->fixPath($target, $cur);

            if(is_array($transport_callback) && count($transport_callback) == 2) {

                /*
                 * Call the transport callback.  If it returns true, do the copy.  False means do not copy this file.
                 * This gives the callback a chance to perform the copy itself in a special way, or ignore a
                 * file/directory
                 */
                if(! call_user_func_array($transport_callback, array($sourcePath, $targetPath)))
                    continue;

            }

            if($this->backend->is_dir($sourcePath)) {

                if($recursive) {

                    $dir = new Dir($sourcePath);

                    $dir->copyTo($targetPath, $recursive, $transport_callback);

                }

            } else {

                $perms = $this->backend->fileperms($sourcePath);

                $this->backend->copy($sourcePath, $targetPath);

                $this->backend->chmod($targetPath, $perms);

            }

        }

        return TRUE;

    }

    public function get($child) {

        return new \Hazaar\File($this->path($child));

    }

}