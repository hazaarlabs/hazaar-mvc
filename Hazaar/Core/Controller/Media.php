<?php

namespace Hazaar\Controller;

class Media extends \Hazaar\Controller\Action {

    private $allowPreview   = array(
        '/^image\//',
        '/application\/pdf/'
    );

    private $cachableParams = array(
        'width', 'height', 'format', 'quality', 'format', 'xwidth', 'xheight', 'filter'
    );

    public function init($request) {

    }

    private function loadConfig() {

        if($configFile = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, 'media.ini')) {

            $config = new\Hazaar\Map();

            $config->fromDotNotation(parse_ini_file($configFile, TRUE));

            return $config;

        }

        return FALSE;

    }

    private function loadSources($config, $connector, $names = array()) {

        if(! is_array($names))
            $names = array($names);

        foreach($config as $id => $source) {

            if($id == 'global' || boolify($source->disabled) || (count($names) > 0 && ! in_array($id, $names)))
                continue;

            if($source->has('type') && $source->has('name')) {

                $manager = new \Hazaar\File\Manager($source->type, $source->get($source->type), $id);

                $connector->addSource($manager, $source->name, $id);

            }

        }

    }

    public function authorise($source) {

        $connector = new \Hazaar\File\BrowserConnector($this->url('browse'), $this->allowPreview);

        if($config = $this->loadConfig())
            $this->loadSources($config, $connector, $source);

        $result = $connector->authorise($source, (string)$this->url(NULL, 'authorise/' . $source));

        if($result) {

            $sess = new \Hazaar\Session();

            $url = $sess->redirect_uri;

        } else {

            $url = (string)new \Hazaar\Application\Url();

        }

        $this->redirect($url);

    }

    public function progress($operation, $data) {

        if($data instanceof \Hazaar\File) {

            $data = array(
                'kind'     => $data->type(),
                'name'     => $data->basename(),
                'path'     => $data->fullpath(),
                'modified' => $data->mtime(),
                'size'     => $data->size(),
                'mime'     => (($data->type() == 'file') ? $data->mime_content_type() : 'dir'),
                'read'     => $data->is_readable(),
                'write'    => $data->is_writable()
            );

        }

        $this->stream(array('progress' => array('operation' => $operation, 'data' => $data)));

    }

    public function __default() {

        $connector = new \Hazaar\File\BrowserConnector($this->url(), $this->allowPreview);

        $connector->setProgressCallback(array($this, 'progress'));

        $cache = FALSE;

        if($config = $this->loadConfig()) {

            if($config->global->has('cache'))
                $cache = boolify($config->global['cache']);

            $this->loadSources($config, $connector);

        }

        if($this->request->has('cmd'))
            return $this->command($this->request->get('cmd'), $connector);

        $target = $this->request->getRawPath();

        $pos = strpos($target, '/');

        if(! ($sourceName = substr($target, 0, $pos)))
            throw new \Exception('Bad Request!', 400);

        $source = $connector->source($sourceName);

        if(! $source)
            throw new \Exception("Media source '$sourceName' is unknown!", 404);

        $target = substr($target, $pos);

        $file = $source->get($target);

        if(! $file->exists())
            throw new \Exception('File not found!', 404);

        $params = $this->request->getParams();

        /*
         * Check if we can offload this preview to the content provider.
         *
         * If a cachable action is requested (ie: resize) then we can use the preview URI.
         *
         * If no actions are requested and the file has a direct URI then redirect to that.
         */
        if(count(array_intersect(array_keys($params), $this->cachableParams)) > 0) {

            if($preview_uri = $file->preview_uri($params))
                $this->redirect($preview_uri);

        } else if($direct_uri = $file->direct_uri()) {

            $direct_uri = new \Hazaar\Http\Uri($direct_uri, $this->request->getParams());

            $this->redirect($direct_uri);

        }

        $response = new \Hazaar\Controller\Response\File($file);

        if(preg_match_array($this->allowPreview, $file->mime_content_type()) > 0)
            $response = new \Hazaar\Controller\Response\Image($file);

	$response->setUnmodified($this->request->getHeader('If-Modified-Since'));

        if($this->request->has('download') && boolify($this->request->download))
            $response->setDownloadable(TRUE);

        if(count(array_intersect(array_keys($params), $this->cachableParams)) > 0) {

            $cache_dir = NULL;

            if($cache = boolify($this->request->get('cache', $cache))) {

                try {

                    $cache_dir = $this->application->runtimePath('imagecache', TRUE);

                } catch(Exception $e) {

                    $cache = FALSE;

                }

            }

            $hash = md5($file->fullpath() . '-' . json_encode($params));

            $cache_file = $cache_file = $cache_dir . '/' . $hash;

            /**
             * Check the cache first for an image with these same request params.
             */
            if($cache && file_exists($cache_file) && filesize($cache_file) > 0) {

                $response->setHeader('X-Media-Cached', 'true');

                $response->setContent(file_get_contents($cache_file));

                $response->setContentType(file_get_contents($cache_file . '.info'));

            } else {

                $params['ratio'] = ($this->request->has('ratio') ? $this->request->ratio : NULL);

                if($this->request->has('format'))
                    $response->setFormat($this->request->format);

                if($thumbnail = $file->thumbnail($params)) {

                    $response->setContent($thumbnail);

                } else {

                    switch($file->mime_content_type()) {
                        case 'application/pdf':
                        case 'image/svg+xml':

                            if(! in_array('imagick', get_loaded_extensions()))
                                throw new \Exception('Not supported', 406);

                            $temp_dir = $this->application->runtimePath('temp', TRUE);

                            $temp_file = $temp_dir . '/' . $hash;

                            file_put_contents($temp_file, $file->get_contents());

                            $pdf = new \Imagick($temp_file . '[0]');

                            $pdf->setResolution(300, 300);

                            $pdf->setImageFormat('png');

                            $response->setContent($pdf->getImageBlob());

                            unlink($temp_file);

                            break;

                        default:

                            if($quality = ake($params, 'quality'))
                                $response->quality(intval($quality));

                            $xwidth = intval(ake($params, 'xwidth'));

                            $xheight = intval(ake($params, 'xheight'));

                            if($xwidth > 0 || $xheight > 0)
                                $response->expand($xwidth, $xheight, ake($params, 'align'), ake($params, 'xoffsettop'), ake($params, 'xoffsetleft'));

                            $width = intval(ake($params, 'width'));

                            $height = intval(ake($params, 'height'));

                            if($width > 0 || $height > 0) {

                                $align = $this->request->get('align', (boolify(ake($params, 'center', 'false')) ? 'center' : NULL));

                                $response->resize($width, $height, boolify(ake($params, 'crop', FALSE)), $align, boolify(ake($params, 'aspect', TRUE)), ! boolify(ake($params, 'enlarge')), ake($params, 'ratio'), intval(ake($params, 'offsettop')), intval(ake($params, 'offsetleft')));

                            }

                            if($filter = ake($params, 'filter'))
                                $response->filter($filter);

                            break;

                    }

                }

                if($cache && is_writable($cache_dir)) {

                    file_put_contents($cache_file, $response->getContent());

                    file_put_contents($cache_file . '.info', $response->getContentType());

                }

            }

        }

        return $response;

    }

    private function command($cmd, $connector) {

        if($cmd == 'authorise') {

            if(intval($_SERVER['REDIRECT_STATUS']) !== 200)
                throw new \Exception('Authorisation process failed!');

            $sess = new \Hazaar\Session();

            $sess->redirect_uri = $_SERVER['HTTP_REFERER'];

            return $connector->authorise($this->request->source, $this->url(NULL, 'authorise/' . $this->request->source));

        } else {

            if(($auth = $connector->authorised()) !== TRUE)
                $this->stream(array('auth' => $auth));

        }

        $reflection = new \ReflectionClass($connector);

        if(! $reflection->hasMethod($cmd))
            throw new \Exception('Bad request');

        unset($this->request->cmd);

        $method = $reflection->getMethod($cmd);

        $params = $method->getParameters();

        $args = array();

        $rqParams = $this->request->getParams();

        if(count($_FILES) > 0) {

            $files = new \Hazaar\File\Upload();

            foreach($files->keys() as $key)
                $rqParams[$key] = $files->get($key);

        }

        foreach($params as $param)
            $args[$param->getPosition()] = ake($rqParams, $param->getName(), ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : NULL));

        ksort($args);

        $response = call_user_func_array(array($connector, $cmd), $args);

        if(strtolower($this->request->getHeader('X-Request-Type')) == 'stream')
            return $this->stream($response);

        return $response;

    }
}
