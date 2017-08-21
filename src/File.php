<?php

namespace Hazaar;

class File {

    protected $backend;

    public    $source_file;

    protected $info;

    protected $mime_content_type;

    /*
     * Any overridden file contents.
     *
     * This is normally used when performing operations on the file in memory, such as resizing an image.
     */
    protected $contents;

    protected $resource;

    protected $handle;

    /*
     * Encryption bits
     */
    static public $default_cipher = 'aes-256-ctr';

    static public $default_key = 'hazaar_secret_badass_key';

    private $encrypted = false;

    function __construct($file = null, $backend = NULL) {

        if($file instanceof \Hazaar\File) {

            $this->backend = $file->backend;

            $this->source_file = $file->source_file;

            $this->info = $file->info;

            $this->mime_content_type = $file->mime_content_type;

        }elseif(is_resource($file)){

            $meta = stream_get_meta_data($file);

            $this->backend = new \Hazaar\File\Backend\Local();

            $this->source_file = $meta['uri'];

            $this->resource = $file;

        } else {

            if(empty($file))
                $file = Application::getInstance()->runtimePath('tmp', true) . '/' . uniqid();

            $this->source_file = $file;

            if(! $backend)
                $backend = new File\Backend\Local(array('root' => ((substr(PHP_OS, 0, 3) == 'WIN') ? substr(APPLICATION_PATH, 0, 3) : '/')));

            if(! $backend instanceof File\Backend\_Interface)
                throw new \Exception('Can not create new file object without a valid file backend!');

            $this->backend = $backend;

        }

    }

    public function __destruct(){

        $this->close();

    }

    public function set_meta($values) {

        return $this->backend->set_meta($this->source_file, $values);

    }

    public function get_meta($key = NULL) {

        return $this->backend->get_meta($this->source_file, $key);

    }

    /*
     * Basic output functions
     */
    public function toString() {

        return $this->fullpath();

    }

    public function __tostring() {

        return $this->toString();

    }

    /*
     * Standard filesystem functions
     */
    public function basename() {

        return basename($this->source_file);

    }

    public function dirname() {

        //Hack: The str_replace() call makes all directory separaters consistent as /.  The use of DIRECTORY_SEPARATOR should only be used in the local backend.
        return str_replace('\\', '/', dirname($this->source_file));

    }

    public function name() {

        return pathinfo($this->source_file, PATHINFO_FILENAME);

    }

    public function fullpath() {

        $dir = $this->dirname();

        return $dir . ((substr($dir, -1, 1) != '/') ? '/' : NULL) . $this->basename();

    }

    public function extension() {

        return pathinfo($this->source_file, PATHINFO_EXTENSION);

    }

    public function size() {

        if($this->contents)
            return strlen($this->contents);

        return $this->backend->filesize($this->source_file);

    }

    /*
     * Standard file modification functions
     */
    public function exists() {

        return $this->backend->exists($this->source_file);

    }

    public function realpath() {

        return $this->backend->realpath($this->source_file);

    }

    public function is_readable() {

        return $this->backend->is_readable($this->source_file);

    }

    public function is_writable() {

        return $this->backend->is_writable($this->source_file);

    }

    public function is_dir() {

        return $this->backend->is_dir($this->source_file);

    }

    public function dir() {

        if($this->is_dir())
            return new File\Dir($this->source_file, $this->backend);

        return FALSE;

    }

    public function is_link() {

        return $this->backend->is_link($this->source_file);

    }

    public function is_file() {

        return $this->backend->is_file($this->source_file);

    }

    public function parent() {

        return new File\Dir($this->dirname(), $this->backend);

    }

    public function type() {

        return $this->backend->filetype($this->source_file);

    }

    public function mtime() {

        return $this->backend->filemtime($this->source_file);

    }

    public function has_contents() {

        return ($this->backend->filesize($this->source_file) > 0);

    }

    /**
     * Returns the current contents of the file.
     *
     * @param mixed $offset
     *
     * @param mixed $maxlen
     *
     * @return mixed
     */
    public function get_contents($offset = -1, $maxlen = NULL) {

        if($this->contents)
            return $this->filter($this->contents);

        return $this->filter($this->backend->read($this->source_file, $offset, $maxlen));

    }

    /**
     * Put contents directly writes data to the storage backend without storing it in the file object itself
     *
     * NOTE: This function is called internally to save data that has been updated in the file object.
     *
     * @param mixed $data The data to write
     *
     * @param mixed $overwrite Overwrite data if it exists
     */
    public function put_contents($data, $overwrite = FALSE) {

        $content_type = null;

        if(!is_resource($this->source_file))
            $content_type = $this->mime_content_type();

        if(! $content_type)
            $content_type = 'text/text';

        return $this->backend->write($this->source_file, $data, $content_type, $overwrite);

    }

    /**
     * Sets the current contents of the file in memory.
     *
     * Calling this function does not directly update the content of the file "on disk".  To do that
     * you must call the File::save() method which will commit the data to storage.
     *
     * @param mixed $bytes The data to set as the content
     */
    public function set_contents($bytes) {

        $this->contents = $bytes;

    }

    /**
     * Set the contents from an encoded string.
     *
     * Currently this supports only data URI encoded strings.  I have made this generic in case I come
     * across other types of encodings that will work with this method.
     *
     * @param mixed $bytes The encoded data
     */
    public function set_decoded_contents($bytes){

        if(substr($bytes, 0, 5) == 'data:'){

            //Check we have a correctly encoded data URI
            if(($pos = strpos($bytes, ',', 5)) === false)
                return false;

            $info = explode(';', substr($bytes, 5, $pos - 5), 2);

            list($content_type, $encoding) = ((count($info) > 1) ? $info : array($info, null));

            if($encoding == 'base64')
                $this->contents = base64_decode(substr($bytes, $pos));
            else
                $this->contents = substr($bytes, $pos);

            if($content_type)
                $this->mime_content_type($content_type);

            return true;

        }

        return false;

    }

    /**
     * Saves the current in-memory content to the storage backend.
     *
     * Internally this calls File::put_contents() to write the data to the backend.
     *
     * @return mixed
     */
    public function save() {

        return $this->put_contents(($this->encrypted ? $this->encrypt(false) : $this->contents), TRUE);

    }

    /**
     * Saves this file objects content to another file name.
     *
     * @param mixed $filename The filename to save as
     *
     * @param mixed $overwrite Boolean flag to indicate that the destination should be overwritten if it exists
     *
     * @return mixed
     */
    public function saveAs($filename, $overwrite = FALSE) {

        return $this->backend->write($filename, $this->contents, $overwrite);

    }

    /**
     * Deletes the file from storage
     *
     * @return mixed
     */
    public function unlink() {

        if(! $this->exists())
            return FALSE;

        if($this->is_dir()) {

            return $this->backend->rmdir($this->source_file, TRUE);

        } else {

            return $this->backend->unlink($this->source_file);

        }

    }

    /**
     * Generate an MD5 checksum of the current file content
     *
     * @return string
     */
    public function md5() {

        //If the contents has been downloaded from the storage backend or updated in some way, use that instead
        if($this->contents)
            return md5($this->contents);

        //Otherwise use the md5 provided by the backend.  This is because some backend providers (such as dropbox) provide
        //a cheap method of calculating the checksum
        return $this->backend->md5Checksum($this->source_file);

    }

    /**
     * Return the base64 encoded content
     *
     * @return string
     */
    public function base64() {

        return base64_encode($this->get_contents());

    }

    /**
     * Returns the contents as decoded JSON.
     *
     * If the content is a JSON encoded string, this will decode the string and return it as a stdClass
     * object, or an associative array if the $assoc parameter is TRUE.
     *
     * If the content can not be decoded because it is not a valid JSON string, this method will return FALSE.
     *
     * @param mixed $assoc Return as an associative array.  Default is to use stdClass.
     *
     * @return mixed
     */
    public function parseJSON($assoc = FALSE) {

        return json_decode($this->get_contents(), $assoc);

    }

    public function copyTo($destination, $create_dest = FALSE, $dstBackend = NULL) {

        if(! $this->exists())
            throw new File\Exception\SourceNotFound($this->source_file, $destination);

        if(! $dstBackend)
            $dstBackend = $this->backend;

        if(! $dstBackend->exists($destination)) {

            if($create_dest)
                $dstBackend->mkdir($destination);

            else
                throw new File\Exception\TargetNotFound($destination, $this->source_file);

        }

        return $dstBackend->copy($this->source_file, $destination, $this->backend);

    }

    public function mime_content_type() {

        if($this->mime_content_type)
            return $this->mime_content_type;

        return $this->backend->mime_content_type($this->fullpath());

    }

    public function set_mime_content_type($type) {

        $this->mime_content_type = $type;

    }

    public function thumbnail($params = array()) {

        return $this->backend->thumbnail($this->fullpath(), $params);

    }

    public function preview_uri($params = array()) {

        return $this->backend->preview_uri($this->fullpath(), $params);

    }

    public function direct_uri() {

        return $this->backend->direct_uri($this->fullpath());

    }

    public function toArray($delimiter = "\n"){

        return explode($delimiter, $this->get_contents());

    }

    /**
     * Return the CSV content as a parsed array
     *
     * @return array
     */
    public function readCSV(){

        return array_map('str_getcsv', $this->toArray("\n"));

    }

    public function unzip($filename){

        $file = false;

        $zip = zip_open($this->source_file);

        if(!is_resource($zip))
            return false;

        while($zip_entry = zip_read($zip)){

            $name = zip_entry_name($zip_entry);

            if(preg_match('/' . $filename . '/', $name)){

                $file = new File($filename, $this->backend);

                $file->set_contents(zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)));

                break;

            }

        }

        zip_close($zip);

        return $file;

    }

    /**
     * Open a file and return it's file handle
     *
     * This is useful for using the file with standard (yet unsupported) file functions.
     *
     * @param string $mode
     *
     * @return resource
     */
    public function open($mode = 'r'){

        if($this->handle)
            return $this->handle;

        return $this->handle = fopen($this->source_file, $mode);

    }

    /**
     * Close the file handle if it is currently open
     *
     * @return boolean
     */
    public function close(){

        if(!$this->handle)
            return false;

        fclose($this->handle);

        $this->handle = null;

        return true;

    }

    /**
     * Returns a character from the file pointer
     *
     * @return string
     */
    public function getc(){

        if(!$this->handle)
            return null;

        return fgetc($this->handle);


    }

    /**
     * Returns a line from the file pointer
     *
     * @return string
     */
    public function gets(){

        if(!$this->handle)
            return null;

        return fgets($this->handle);

    }

    /**
     * Returns a line from the file pointer and strips HTML tags
     *
     * @return string
     */
    public function getss($allowable_tags = null){

        if(!$this->handle)
            return null;

        return strip_tags(fgets($this->handle), $allowable_tags);

    }

    /**
     * Returns a line from the file pointer and parse for CSV fields
     *
     * @param mixed $length     Must be greater than the longest line (in characters) to be found in the CSV file
     *                          (allowing for trailing line-end characters). Otherwise the line is split in chunks
     *                          of length characters, unless the split would occur inside an enclosure.
     *
     *                          Omitting this parameter (or setting it to 0 in PHP 5.1.0 and later) the maximum
     *                          line length is not limited, which is slightly slower.
     * @param mixed $delimiter  The optional delimiter parameter sets the field delimiter (one character only).
     * @param mixed $enclosure  The optional enclosure parameter sets the field enclosure character (one character only).
     * @param mixed $escape     The optional escape parameter sets the escape character (one character only).
     *
     * @return \array|null
     */
    public function getcsv($length = 0, $delimiter = ',', $enclosure = '"', $escape = '\\'){

        if(!$this->handle)
            return null;

        return fgetcsv($this->handle, $length, $delimiter, $enclosure, $escape);

    }

    /**
     * Writes an array to the file in CSV format.
     *
     * @param mixed $fields     Must be greater than the longest line (in characters) to be found in the CSV file
     *                          (allowing for trailing line-end characters). Otherwise the line is split in chunks
     *                          of length characters, unless the split would occur inside an enclosure.
     *
     *                          Omitting this parameter (or setting it to 0 in PHP 5.1.0 and later) the maximum
     *                          line length is not limited, which is slightly slower.
     * @param mixed $delimiter  The optional delimiter parameter sets the field delimiter (one character only).
     * @param mixed $enclosure  The optional enclosure parameter sets the field enclosure character (one character only).
     * @param mixed $escape     The optional escape parameter sets the escape character (one character only).
     *
     * @return \integer|null
     */
    public function putcsv($fields, $delimiter = ',', $enclosure = '"', $escape = '\\'){

        if(!($this->handle && is_array($fields)))
            return null;

        return fputcsv($this->handle, $fields, $delimiter, $enclosure, $escape);

    }

    /**
     * Check if a file is encrypted using the built-in Hazaar encryption method
     *
     * @return boolean
     */
    public function isEncrypted(){

        $r = $this->open();

        $bom = pack('H*','BADA55');  //Haha, Bad Ass!

        $encrypted = (fread($r, 3) == $bom);

        $this->close();

        return $encrypted;

    }

    private function getEncryptionKey(){

        if($key_file = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, '.key'))
            $key = trim(file_get_contents($key_file));
        else
            $key = File::$default_key;

        return md5($key);

    }

    /**
     * Internal content filter
     *
     * Checks if the content is modified in some way using a BOM mask.  Currently this is used to determine if a file
     * is encrypted and automatically decrypts it if an encryption key is available.
     *
     * @param mixed $content
     * @return mixed
     */
    private function filter($content){

        $bom = pack('H*','BADA55');

        //Check if we are encrypted
        if(substr($content, 0, 3) !== $bom)
            return $content;

        $this->encrypted = true;

        $cipher_len = openssl_cipher_iv_length(File::$default_cipher);

        $iv = substr($content, 3, $cipher_len);

        return openssl_decrypt(substr($content, 3 + $cipher_len), File::$default_cipher, $this->getEncryptionKey(), OPENSSL_RAW_DATA, $iv);

    }

    public function encrypt($write = true){

        $bom = pack('H*','BADA55');

        $content = $this->get_contents();

        if(substr($content, 0, 3) == $bom)
            return $this->contents;

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(File::$default_cipher));

        $data = openssl_encrypt($content, File::$default_cipher, $this->getEncryptionKey(), OPENSSL_RAW_DATA, $iv);

        $this->contents = $bom . $iv . $data;

        if($write)
            $this->save();

        $this->encrypted = true;

        return $this->contents;

    }

    /**
     * Writes the decrypted file to storage
     */
    public function decrypt(){

        $this->contents = $this->get_contents();

        $this->encrypted = false;

        return $this->save();

    }

    static public function delete($path){

        $file = new File($path);

        return $file->unlink();

    }

}