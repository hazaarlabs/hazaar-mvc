<?php

namespace Hazaar\File\Backend;

use \Hazaar\Http\Request;

class SharePoint extends \Hazaar\Http\Client implements _Interface {

    public  $separator  = '/';

    private $options;

    private $cache;

    private static $STSAuthURL = 'https://login.microsoftonline.com/extSTS.srf';

    private static $signInURL = '/_forms/default.aspx?wa=wsignin1.0';

    private $requestFormDigest;

    private $root = null;

    static public function label(){

        return "Microsoft SharePoint";

    }

    public function __construct($options) {

        parent::__construct();

        $this->disableRedirect();

        $this->options = new \Hazaar\Map(array(
            'webURL'        => '',
            'username'      => '',
            'password'      => '',
            'root'          => 'Shared Documents',
            'cache_backend' => 'file'
        ), $options);

        if($this->options->isEmpty('webURL') || $this->options->isEmpty('username') || $this->options->isEmpty('password'))
            throw new Exception\DropboxError('SharePoint filesystem backend requires a webURL, username and password.');

        $cache_options = array(
            'use_pragma' => FALSE,
            'namespace' => 'sharepoint_' . md5($this->options->username . ':' . $this->options->password . '@' . $this->options->webURL)
        );

        $this->cache = new \Hazaar\Cache($this->options['cache_backend'], $cache_options);

        $this->uncacheCookie($this->cache);

        $this->root = new \stdClass;

    }

    public function __destruct() {

    }

    public function reload() {

    }

    public function reset() {

    }

    public function authorise($redirect_uri = NULL) {

        if($this->authorised())
            return true;

        if(!($token = $this->getSecurityToken($this->options['username'], $this->options['password'])))
            throw new \Exception('Unable to get SharePoint security token!');

        return $this->getAuthenticationCookies($token);

    }

    private function getSecurityToken($username, $password){

        $xmlFile = __DIR__ . DIRECTORY_SEPARATOR . 'XML' . DIRECTORY_SEPARATOR . 'SAML.xml';

        if(!file_exists($xmlFile))
            throw new \Exception('SAML XML authorisation template is missing!');

        $request = new Request(self::$STSAuthURL, 'POST');

        $request->setHeader('Accept', 'application/json; odata=verbose');

        $template = file_get_contents($xmlFile);

        $template = str_replace('{username}', $this->options['username'], $template);

        $template = str_replace('{password}', $this->options['password'], $template);

        $template = str_replace('{address}', $this->options['webURL'], $template);

        $request->setBody($template);

        $response = $this->send($request);

        if($response->status !== 200)
            throw new \Exception('Invalid response requesting security token.');

        $xml = new \DOMDocument();

        $xml->loadXML($response->body());

        if(!$xml instanceof \DOMDocument)
            throw new \Exception('Invalid response authenticating SharePoint access.');

        $xpath = new \DOMXPath($xml);

        if ($xpath->query("//wsse:BinarySecurityToken")->length > 0){

            $nodeToken = $xpath->query("//wsse:BinarySecurityToken")->item(0);

            if (!empty($nodeToken))
                return $nodeToken->nodeValue;

        }

        if ($xpath->query("//S:Fault")->length > 0)
            throw new \RuntimeException($xpath->query("//S:Fault")->item(0)->nodeValue);

        return false;

    }

    private function getAuthenticationCookies($token){

        $url_info = parse_url($this->options['webURL']);

        $url =  $url_info['scheme'] . '://' . $url_info['host'] . self::$signInURL;

        $request = new Request($url, 'POST');

        $request->setBody($token);

        $response = $this->send($request, false);

        if($response->status !== 302)
            throw new \Exception('Invalid response requesting auth cookies: ' . $response->status);

        $this->deleteCookie('fpc');

        $this->deleteCookie('x-ms-gateway-slice');

        $this->deleteCookie('stsservicecookie');

        $this->deleteCookie('RpsContextCookie');

        $this->cacheCookie($this->cache);

        return true;

    }

    public function authorised() {

        return ($this->hasCookie('FedAuth') && $this->hasCookie('rtFa'));

    }

    public function refresh($reset = FALSE) {

    }

    private function resolvePath($path){

        $parts = explode('/', trim($this->options['root'] . '/' . ltrim($path, ' /'), '/'));

        array_walk($parts, function(&$value){ $value = rawurlencode($value); });

        return implode('/', $parts);

    }

    private function _load_meta($path, $suffix = null, &$response = null){

        $url = $this->_url($path, $suffix) ;

        return $this->_query($url, 'GET', $suffix, $response);

    }

    private function _getFormDigest(){

        if(!$this->requestFormDigest){

            $this->authorise();

            $request = new Request($this->options['webURL'] . '/_api/contextinfo', 'POST');

            $request->setHeader('Accept', 'application/json; OData=verbose');

            $response = $this->send($request);

            $this->requestFormDigest = ake($response->body(), 'd.GetContextWebInformation.FormDigestValue');

        }

        return $this->requestFormDigest;

    }

    private function _query($url, $method = 'GET', $body = null, $extra_headers = null, &$response = null){

        $this->authorise();

        if($method === 'POST' || $method === 'PUT')
            $extra_headers['X-RequestDigest'] = $this->_getFormDigest();

        $request = new Request($url, $method, 'application/json; OData=verbose');

        $request->setHeader('Accept', 'application/json; OData=verbose');

        if(is_array($extra_headers)){

            foreach($extra_headers as $key => $value)
                $request->setHeader($key, $value);

        }

        if($body !== null)
            $request->setBody((($body instanceof \stdClass || is_array($body) ? json_encode($body) : $body)));

        $response = $this->send($request);

        if($response->status !== 200 && $response->status !== 201){

            $error = ake($response->body(), 'error');

            throw new \Exception('Invalid response (' . $response->status . ') from SharePoint: code=' . $error->code . ' message=' . $error->message->value);

        }

        return $response->body();

    }

    private function _object_url($path){

        if(!($info = $this->info($path)))
            return false;

        return ($info->__metadata->type === 'SP.Folder')
            ? $this->_folder($path)
            : $this->_folder(dirname($path)) . "/Files('" . rawurlencode(basename($path)) . "')";

    }

    private function _web($path = null){

        return $this->options['webURL'] . '/_api/Web' . ($path ? '/' . ltrim($path) : '');

    }

    private function _folder($path = null, $suffix = null){

        if($path = $this->resolvePath($path))
            $path = "GetFolderByServerRelativeUrl('$path')";

        $url = $this->_web($path);

        if($suffix !== null)
            $url .= '/' . $suffix;

        return $url;

    }

    private function _file($path = null, $suffix = null){

        if($path = $this->resolvePath($path))
            $path = "GetFileByServerRelativeUrl('$path')";

        $url = $this->_web($path);

        if($suffix !== null)
            $url .= '/' . $suffix;

        return $url;

    }

    private function &info($path, $new_item = null){

        $folder =& $this->root;

        $parts = explode('/', trim($path, ' /'));

        //If there's no item, we're loading the root, so make some stuff up.
        if(!($item = array_pop($parts)) && !property_exists($folder, 'Name'))
            $folder = ake($this->_query($this->_folder('/')), 'd');

        foreach($parts as $part){

            if(!property_exists($folder, 'items'))
                $folder->items = array();

            if(!array_key_exists($part, $folder->items))
                $folder->items[$part] = (object)array('Name' => $part);

            $folder =& $folder->items[$part];

        }

        if(!$item)
            return $folder;

        if(!($folder instanceof \stdClass && property_exists($folder, 'items')))
            $folder->items = $this->load(implode('/', $parts));

        foreach($folder->items as &$f){

            if($f->Name === $item){

                if($new_item !== null)
                    $f = $new_item;

                return $f;

            }

        }

        if($new_item !== null){

            $folder->items[] = $new_item;

            return $new_item;

        }

        $null = null;

        return $null;

    }

    private function load($path){

        $this->authorise();

        $url = $this->options['webURL'] . '/_api/$batch';

        $request = new Request($url, 'POST');

        $request->setHeader('Accept', 'application/json; OData=verbose');

        $request->setHeader('X-RequestDigest', $this->_getFormDigest());

        $request->setHeader('X-RequestDigest', $this->_getFormDigest());

        $request->enableMultipart('multipart/mixed', 'batch_' . guid());

        $headers = array('Content-Transfer-Encoding' => 'binary');

        $request->addMultipart('GET ' . $this->_folder($path, 'folders')
            . " HTTP/1.1\nAccept: application/json; OData=verbose\n", 'application/http', $headers);

        $request->addMultipart('GET ' . $this->_folder($path, 'files')
            . " HTTP/1.1\nAccept: application/json; OData=verbose\n", 'application/http', $headers);

        $response = $this->send($request);

        if($response->status !== 200)
            throw new \Exception('Invalid batch response received!');

        $responses = $response->body();

        if(count($responses) !== 2)
            throw new \Exception('Batch request error.  Requested 2 responses, got ' . count($responses));

        array_walk($responses, function(&$value){
            $response = new \Hazaar\Http\Response();

            $response->read($value['body']);

            $value = $response;
        });

        $folders = ake($responses[0]->body(), 'd.results');

        $files = ake($responses[1]->body(), 'd.results');

        $sort = function($a, $b){
            if ($a->Name === $b->Name) return 0;
            return ($a->Name < $b->Name) ? -1 : 1;
        };

        usort($folders, $sort);

        usort($files, $sort);

        $items = array();

        foreach(array_merge($folders, $files) as $item)
            $items[$item->Name] = $item;

        return $items;

    }

    //Get a directory listing
    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE) {

        $info =& $this->info($path);

        if(!isset($info->items) || $info->ItemCount !== count($info->items))
            $info->items = $this->load($path);

        $files = array();

        foreach($info->items as $item)
            $files[] = $item->Name;

        return $files;

    }

    //Check if file/path exists
    public function exists($path) {

        if(!($info = $this->info($path)))
            return false;

        return $info->Exists;

    }

    public function realpath($path) {

        if(!($info = $this->info($path)))
            return null;

        return $info->ServerRelativeUrl;

    }

    public function is_readable($path) {

        if($this->info($path))
            return true;

        return false;

    }

    public function is_writable($path) {

        if($this->info($path))
            return true;

        return false;

    }

    //TRUE if path is a directory
    public function is_dir($path) {

        if(!($info = $this->info($path)))
            return false;

        return $info->__metadata->type === 'SP.Folder';

    }

    //TRUE if path is a symlink
    public function is_link($path) {

        return false;

    }

    //TRUE if path is a normal file
    public function is_file($path) {

        if(!($info = $this->info($path)))
            return false;

        return $info->__metadata->type === 'SP.File';

    }

    //Returns the file type
    public function filetype($path) {

        if(!($info = $this->info($path)))
            return false;

        $types = array('SP.Folder' => 'dir', 'SP.File' => 'file');

        return ake($types, $info->__metadata->type, false);

    }

    //Returns the file modification time
    public function filectime($path) {

        if(!($info = $this->info($path)))
            return null;

        return strtotime($info->TimeCreated);

    }

    //Returns the file modification time
    public function filemtime($path) {

        if(!($info = $this->info($path)))
            return null;

        return strtotime($info->TimeLastModified);

    }

    //Returns the file modification time
    public function fileatime($path) {

        return time();

    }

    public function filesize($path) {

        if(!($info = $this->info($path)))
            return false;

        return intval(ake($info, 'Length', 0));

    }

    public function fileperms($path) {

        return false;

    }

    public function chmod($path, $mode) {

        return false;

    }

    public function chown($path, $user) {

        return false;

    }

    public function chgrp($path, $group) {

        return false;

    }

    public function unlink($path) {

        $url = $this->_object_url($path);

        $this->_query($url, 'POST', null, array('X-HTTP-Method' => 'DELETE'));

        return true;

    }

    public function mime_content_type($path, $hint = null) {

        if($type = Local::lookup_content_type(pathinfo($path, PATHINFO_EXTENSION)))
            return $type;

        return false;

    }

    public function md5Checksum($path) {

        if(!($info = $this->info($path)))
            return false;

        return md5($info->ContentTag);

    }

    public function thumbnail($path, $params = array()) {

        return false;

    }

    //Create a directory
    public function mkdir($path) {

        $url = $this->_web('folders');

        $body = array(
            '__metadata' => array(
                'type' => 'SP.Folder'
            ),
            'ServerRelativeUrl' => $this->options['root'] . $path
        );

        $result = $this->_query($url, 'POST', $body);

        return (ake($result, 'd.Exists') === true);

    }

    public function rmdir($path, $recurse = false) {

        $url = $this->_folder($path);

        $this->_query($url, 'POST', null, array('X-HTTP-Method' => 'DELETE'));

        return true;

    }

    //Copy a file from src to dst
    public function copy($src, $dst, $recursive = FALSE) {

        $dst = parse_url($this->options['webURL'], PHP_URL_PATH) . '/' . $this->resolvePath($dst) . '/' . rawurlencode(basename($src));

        $url = $this->_object_url($src) . "/copyTo('$dst')";

        $result = $this->_query($url, 'POST');

        return ($result instanceof \stdClass && property_exists($result, 'd'));

    }

    public function link($src, $dst) {

        return false;

    }

    //Move a file from src to dst
    public function move($src, $dst) {

        $dst = parse_url($this->options['webURL'], PHP_URL_PATH) . '/' . $this->resolvePath($dst) . '/' . rawurlencode(basename($src));

        $url = $this->_object_url($src) . "/moveTo('$dst')";

        $result = $this->_query($url, 'POST');

        return ($result instanceof \stdClass && property_exists($result, 'd'));

    }

    //Read the contents of a file
    public function read($path) {

        return $this->_query($this->_folder(dirname($path)) . "/Files('" . rawurlencode(basename($path)) . "')/\$value");

    }

    //Write the contents of a file
    public function write($file, $data, $content_type, $overwrite = FALSE) {

        $url = $this->_folder(dirname($file), "Files/add(url='" . rawurlencode(basename($file)) . "',overwrite=" . strbool($overwrite) . ")");

        if(!($result = $this->_query($url, 'POST', $data, null, $response)))
            return false;

        $folder =& $this->root;

        $parts = explode('/', trim(dirname($file), '/ '));

        //Update any existing file metadata.  Here, if the metadata has not been loaded then we don't need to update.
        foreach($parts as $part){

            if(!$part)
                continue;

            if(!(property_exists($folder, 'items') && array_key_exists($part, $folder->items)))
                break;

            $folder =& $folder->items[$part];

        }

        if($folder instanceof \stdClass && property_exists($folder, 'items') && array_key_exists(basename($file), $folder->items))
            $folder->items[basename($file)] = ake($result, 'd');

        return true;

    }

    public function upload($path, $file, $overwrite = TRUE) {

        return $this->write(rtrim($path, ' /') . '/' . $file['name'], file_get_contents($file['tmp_name']), null, $overwrite);

    }

    public function set_meta($path, $values) {

        return false;

    }

    public function get_meta($path, $key = NULL) {

        return false;

    }

    public function preview_uri($path) {

        return null;

    }

    public function direct_uri($path) {

        if(!($info = $this->info($path)))
            return false;

        if($info->__metadata->type === 'SP.Folder')
            return null;

        return $info->LinkingUri;

    }

}
