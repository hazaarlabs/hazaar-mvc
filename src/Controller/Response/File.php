<?php

namespace Hazaar\Controller\Response;

class File extends \Hazaar\Controller\Response\HTTP\OK {

    protected $file;

    protected $backend;

    protected $fmtime;

    /**
     * Default cache control
     * 
     * Public with max age is 5 minutes
     */
    static public $__default_cache_control_directives = [
        'public'  => false,
        'max-age' => 300
    ];

    /**
     * Byte-Order-Mark
     * 
     * This allows a byte-order-mark to be output at the beginning of the file content if one does not already exist.
     */
    private $bom = null; 

    private $charset_map = [
        'utf-8'     => "EFBBBF",
        'utf-16'    => "FEFF",
        'utf-16be'  => "FEFF",
        'utf-16le'  => "FFFE",
        'utf-32'    => "0000FEFF",
        'utf-32be'  => "0000FEFF",
        'utf-32le'  => "FFFE0000",
    ];

    /**
     * \Hazaar\File Constructor
     *
     * @param mixed $file Either a string filename to use or a \Hazaar\File object.
     *
     * @throws \Hazaar\Exception\FileNotFound
     */
    function __construct($file = NULL, $backend = NULL) {

        $this->backend = $backend;

        parent::__construct();

        $this->initialiseCacheControl();

        if($file !== NULL)
            $this->load($file, $backend);

    }

    public function initialiseCacheControl(){

        $cache_config = \Hazaar\Application::getInstance()->config->get('http.cacheControl', self::$__default_cache_control_directives, true);

        if($cacheControlHeader = ake(apache_request_headers(), 'Cache-Control')){

            $replyable = ['no-cache', 'no-store', 'no-transform'];

            $parts = explode(',', $cacheControlHeader);

            foreach($parts as $part){

                if(substr($part, 0, 7) === 'max-age'){

                    $cache_config->set('max-age', intval(substr($part, strpos($part, '=', 7) + 1)));

                    break;

                }elseif(in_array(strtolower(trim($part)), $replyable)){
                    
                    $cache_config->set('reply', $part);

                }
                
            }

        }

        $cache_control = [];

        if($cache_config->has('reply'))
            $cache_control[] = $cache_config->get('reply');
        elseif($cache_config->get('no-store') === true)
            $cache_control[] = 'no-store';
        elseif($cache_config->get('no-cache') === true)
            $cache_control[] = 'no-cache';
        elseif($cache_config->has('public'))
            $cache_control[] = $cache_config->get('public') ? 'public' : 'private';
        elseif($cache_config->has('private'))
            $cache_control[] = $cache_config->get('private') ? 'private' : 'public';

        if($cache_config->has('max-age') 
            && !($cache_config->reply === 'no-cache' 
                || $cache_config->reply === 'no-store'
                || $cache_config->get('no-cache') === true
                || $cache_config->get('no-store') === true))
            $cache_control[] = 'max-age=' . $cache_config->get('max-age');

        if(count($cache_control) > 0)
            return $this->setHeader('Cache-Control', implode(', ', $cache_control));

        return false;

    }

    public function load($file, $backend = NULL) {

        if(! $backend)
            $backend = $this->backend;

        $this->file = ($file instanceof \Hazaar\File) ? $file : new \Hazaar\File($file, $backend);

        if(!($this->file->exists() || $this->hasContent()))
            return false;

        $this->setContentType($this->file->mime_content_type());

        $this->setLastModified($this->file->mtime());

        return TRUE;

    }

    public function setContent($data, $content_type = null) {

        if($data instanceof \Hazaar\File){

            $this->file = $data;

        }elseif(! $this->file){

            $this->file = new \Hazaar\File(NULL);

        }

        if($content_type)
            $this->file->set_mime_content_type($this->content_type = $content_type);

        $this->file->set_contents($data);

        return $this->file;

    }

    public function getContent() {

        if($this->file)
        {
            $content = $this->file->get_contents();
        }else
        {
            $content = parent::getContent();
        }
        foreach($this->charset_map as $bom)
        {
            if(substr($content, 0, strlen($bom)) !== $bom)
                continue;
            $this->bom = null;
            break;
        }
        return $this->bom . $content;
    }

    public function getContentLength() {

        if($this->file)
            return $this->file->size();

        return 0;

    }

    public function hasContent() {

        if($this->file)
            return ($this->file->size() > 0);

        return FALSE;

    }

    public function setUnmodified($ifModifiedSince) {

        if(!$this->file)
            return false;

        if(!$ifModifiedSince)
            return false;

        if(!$ifModifiedSince instanceof \Hazaar\Date)
            $ifModifiedSince = new \Hazaar\Date($ifModifiedSince);

        if(($this->fmtime ? $this->fmtime->sec() : $this->file->mtime()) > $ifModifiedSince->getTimestamp())
            return false;

        $this->setStatus(304);

        return true;

    }

    public function setLastModified($fmtime) {

        if(! $fmtime instanceof \Hazaar\Date)
            $fmtime = new \Hazaar\Date($fmtime, 'UTC');

        $this->fmtime = $fmtime;

        $this->setHeader('Last-Modified', gmdate('r', $this->fmtime->sec()));

    }

    public function getLastModified() {

        return $this->getHeader('Last-Modified');

    }

    public function match($pattern, &$matches = NULL, $flags = 0, $offset = 0) {

        return preg_match($pattern, $this->content, $matches, $flags, $offset);

    }

    public function replace($pattern, $replacement, $limit = -1, &$count = NULL) {

        return $this->content = preg_replace($pattern, $replacement, $this->content, $limit, $count);

    }

    public function setDownloadable($toggle = TRUE, $filename = NULL) {

        if(! $filename)
            $filename = $this->file->basename();

        if($toggle) {

            $this->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } else {

            $this->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"');

        }

    }

    public function setContentType($type = null){

        parent::setContentType($type);

        if(($colon_pos = strpos($this->content_type, ';')) === false)
            return;

        $options = array_change_key_case(array_unflatten(trim(substr($this->content_type, $colon_pos + 1))), CASE_LOWER);

        if(!array_key_exists('charset', $options))
            return;

        $options = array_map('strtolower', array_map('trim', $options));

        if(!array_key_exists($options['charset'], $this->charset_map))
            return;

        $this->bom = pack('H*', $this->charset_map[$options['charset']]);

    }

    public function getContentType() {

        return $this->content_type ? $this->content_type : $this->file->mime_content_type();

    }

    public function getFile() {

        return $this->file;

    }

}