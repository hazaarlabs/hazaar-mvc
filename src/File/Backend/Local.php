<?php

namespace Hazaar\File\Backend;

class Local implements _Interface {

    public  $separator  = DIRECTORY_SEPARATOR;

    private $options;

    static  $mime_types = NULL;

    private $meta       = array();

    public function __construct($options = array()) {

        $root = ((substr(PHP_OS, 0, 3) == 'WIN') ? substr(APPLICATION_PATH, 0, 3) : DIRECTORY_SEPARATOR);

        $this->options = new \Hazaar\Map(array('display_hidden' => FALSE, 'root' => $root), $options);

        $metafile = $this->options->root . DIRECTORY_SEPARATOR . '.metadata';

        if(file_exists($metafile) && $meta = json_decode(file_get_contents($metafile), TRUE))
            $this->meta = $meta;

    }

    public function __destruct() {

        if(is_array($this->meta) && count($this->meta) > 0 && $this->options->root != DIRECTORY_SEPARATOR)
            file_put_contents($this->options->root . DIRECTORY_SEPARATOR . '.metadata', json_encode($this->meta));

    }

    public function refresh($reset = FALSE) {

        return TRUE;

    }

    public function resolvePath($path, $file = NULL) {

        $path = \Hazaar\Loader::fixDirectorySeparator($path);

        $base = $this->options->get('root', DIRECTORY_SEPARATOR);

        if($path == DIRECTORY_SEPARATOR)
            $path = $base;
        elseif(substr($path, 1, 1) !== ':') //Not an absolute Windows path
            $path = $base . ((substr($base, -1, 1) != DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : NULL) . trim($path, DIRECTORY_SEPARATOR);

        if($file)
            $path .= ((strlen($path) > 1) ? DIRECTORY_SEPARATOR : NULL) . trim($file, DIRECTORY_SEPARATOR);

        return $path;

    }

    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE) {

        $list = array();

        $path = $this->resolvePath($path);

        if(! is_dir($path))
            return FALSE;

        $dir = dir($path);

        while(($file = $dir->read()) != FALSE) {

            if($file == '.metadata')
                continue;

            if(($show_hidden == FALSE && substr($file, 0, 1) == '.') || $file == '.' || $file == '..')
                continue;

            if($regex_filter && ! preg_match($regex_filter, $file))
                continue;

            $list[] = $file;

        }

        return $list;

    }

    public function read($file, $offset = -1, $maxlen = NULL) {

        $file = $this->resolvePath($file);

        $ret = FALSE;

        if(file_exists($file)) {

            if($offset >= 0) {

                if($maxlen) {

                    $ret = file_get_contents($file, FALSE, NULL, $offset, $maxlen);

                } else {

                    $ret = file_get_contents($file, FALSE, NULL, $offset);

                }

            } else {

                $ret = file_get_contents($file);

            }

        }

        return $ret;

    }

    public function write($file, $data, $content_type, $overwrite = TRUE) {

        $file = $this->resolvePath($file);

        if(file_exists($file) && $overwrite == FALSE)
            return FALSE;

        if(($ret = file_put_contents($file, $data)) !== FALSE)
            return TRUE;

        return FALSE;

    }

    public function upload($path, $file, $overwrite = TRUE) {

        $path = \Hazaar\Loader::fixDirectorySeparator($path);

        $fullPath = $this->resolvePath(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file['name']);

        if(file_exists($fullPath) && $overwrite == FALSE)
            return FALSE;

        return move_uploaded_file($file['tmp_name'], $fullPath);

    }

    public function copy($src, $dst, $recursive = FALSE) {

        $src = rtrim(\Hazaar\Loader::fixDirectorySeparator($src), DIRECTORY_SEPARATOR);

        $dst = rtrim(\Hazaar\Loader::fixDirectorySeparator($dst), DIRECTORY_SEPARATOR);

        if($this->is_file($src)) {

            $rSrc = $this->resolvePath($src);

            $rDst = $this->resolvePath($dst);

            if($this->is_dir($dst))
                $rDst = $this->resolvePath($dst, basename($src));

            $ret = copy($rSrc, $rDst);

            if($ret) {

                if($srcMeta = ake($this->meta, $rSrc))
                    $this->meta[$rDst] = $srcMeta;

                return TRUE;

            }

        } elseif($this->is_dir($src) && $recursive) {

            $dst .= DIRECTORY_SEPARATOR . basename($src);

            if(! $this->exists($dst))
                $this->mkdir($dst);

            $dir = $this->scandir($src);

            foreach($dir as $file) {

                $fullpath = $src . DIRECTORY_SEPARATOR . $file;

                if($this->is_dir($fullpath))
                    $this->copy($fullpath, $dst, TRUE);

                else
                    $this->copy($fullpath, $dst);

            }

            return TRUE;

        }

        return FALSE;

    }

    public function link($src, $dst) {

        $rSrc = $this->resolvePath($src);

        $rDst = $this->resolvePath($dst);

        if(file_exists($rDst))
            return FALSE;

        return link($rSrc, $rDst);

    }

    public function move($src, $dst) {

        $rSrc = $this->resolvePath($src);

        $rDst = $this->resolvePath($dst);

        if(is_dir($rDst))
            $rDst = $this->resolvePath($dst, basename($src));

        if(substr($dst, 0, strlen($src)) == $src)
            return FALSE;

        $ret = rename($rSrc, $rDst);

        if($ret) {

            if($srcMeta = ake($this->meta, $rSrc)) {

                $this->meta[$rDst] = $srcMeta;

                unset($this->meta[$rSrc]);

            }

            return TRUE;

        }

        return FALSE;

    }

    public function unlink($path) {

        $realPath = $this->resolvePath($path);

        if((file_exists($realPath) || is_link($realPath)) && ! is_dir($realPath)) {

            $ret = unlink($realPath);

            if($ret) {

                if(is_array($this->meta) && array_key_exists($realPath, $this->meta))
                    unset($this->meta[$realPath]);

                return TRUE;

            }

        }

        return FALSE;

    }

    public function mime_content_type($path) {

        $path = $this->resolvePath($path);

        if(! file_exists($path))
            return NULL;

        $info = pathinfo($path);

        if($extension = ake($info, 'extension')) {

            if(! is_array(Local::$mime_types)) {

                Local::$mime_types = array();

                $mt_file = \Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, 'mime.types');

                $h = fopen($mt_file, 'r');

                while($line = fgets($h)) {

                    $line = trim($line);

                    if(substr($line, 0, 1) == '#' || strlen($line) == 0)
                        continue;

                    if(preg_match('/^(\S*)\s*(.*)$/', $line, $matches)) {

                        $extens = explode(' ', $matches[2]);

                        foreach($extens as $value) {
                            if($value)
                                Local::$mime_types[strtolower($value)] = $matches[1];
                        }

                    }

                }

                fclose($h);

            }

            if($type = ake(Local::$mime_types, $extension))
                return $type;

        }

        if(function_exists('finfo_open')){

            $const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME;

            $mime = finfo_open($const);

            if(! empty($mime)) {

                if($type = finfo_file($mime, $path))
                    return $type;

            }

        }

        return 'text/text';

    }

    public function md5Checksum($path) {

        if($path = $this->resolvePath($path))
            return md5_file($path);

        return NULL;

    }

    public function thumbnail($path, $params = array()) {

        return FALSE;

    }

    public function mkdir($path) {

        $path = $this->resolvePath($path);

        if(file_exists($path))
            return FALSE;

        return mkdir($path);

    }

    public function rmdir($path, $recurse = FALSE) {

        $realPath = $this->resolvePath($path);

        if(! is_dir($realPath))
            return FALSE;

        if($recurse) {

            $dir = $this->scandir($path, NULL, TRUE);

            foreach($dir as $file) {

                if($file == '.' || $file == '..')
                    continue;

                $fullpath = $path . DIRECTORY_SEPARATOR . $file;

                if($this->is_dir($fullpath)) {

                    $this->rmdir($fullpath, TRUE);

                } else {

                    $this->unlink($fullpath);

                }

            }

        }

        if($path == DIRECTORY_SEPARATOR)
            return TRUE;

        //Hack to get PHP on windows to let go of the now empty directory so that we can remove it
        $handle = opendir($realPath);

        closedir($handle);

        return rmdir($realPath);

    }

    //Check if file/path exists
    public function exists($path) {

        return file_exists($this->resolvePath($path));

    }

    public function realpath($path) {

        return realpath($this->resolvePath($path));

    }

    public function is_readable($path) {

        return is_readable($this->resolvePath($path));

    }

    public function is_writable($path) {

        return is_writable($this->resolvePath($path));

    }

    //TRUE if path is a directory
    public function is_dir($path) {

        return is_dir($this->resolvePath($path));

    }

    //TRUE if path is a symlink
    public function is_link($path) {

        return is_link($this->resolvePath($path));

    }

    //TRUE if path is a normal file
    public function is_file($path) {

        return is_file($this->resolvePath($path));

    }

    //Returns the file type
    public function filetype($path) {

        return filetype($this->resolvePath($path));

    }

    //Returns the file create time
    public function filectime($path) {

        return filectime($this->resolvePath($path));

    }

    //Returns the file modification time
    public function filemtime($path) {

        return filemtime($this->resolvePath($path));

    }

    //Returns the file access time
    public function fileatime($path) {

        return fileatime($this->resolvePath($path));

    }

    public function filesize($path) {

        return filesize($this->resolvePath($path));

    }

    public function fileperms($path) {

        return fileperms($this->resolvePath($path));

    }

    public function chmod($path, $mode) {

        return chmod($this->resolvePath($path), $mode);

    }

    public function chown($path, $user) {

        return chown($this->resolvePath($path), $user);

    }

    public function chgrp($path, $group) {

        return chgrp($this->resolvePath($path), $group);

    }

    private function meta($path) {

        $fullpath = $this->resolvePath($path);

        if($meta = ake($this->meta, $fullpath))
            return $meta;

        $meta = array();

        /**
         * Generate Image Meta
         */
        if(substr($this->mime_content_type($path), 0, 5) == 'image') {

            $size = getimagesize($fullpath);

            $meta['width'] = $size[0];

            $meta['height'] = $size[1];

            $meta['bits'] = ake($size, 'bits');

            $meta['channels'] = ake($size, 'channels');

        }

        $this->meta[$fullpath] = $meta;

        return $meta;

    }

    public function set_meta($path, $values) {

        $fullpath = $this->resolvePath($path);

        if(! ($meta = ake($this->meta, $fullpath)))
            return NULL;

        $this->meta[$fullpath] = array_merge($this->meta[$fullpath], $values);

        return TRUE;

    }

    public function get_meta($path, $key = NULL) {

        if(! ($meta = $this->meta($path)))
            return NULL;

        if($key)
            return ake($meta, $key);

        return $meta;

    }

    public function preview_uri($path) {

        return FALSE;

    }

    public function direct_uri($path) {

        return FALSE;

    }

}
