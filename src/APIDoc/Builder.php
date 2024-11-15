<?php

namespace Application\Model\Common\APIDoc;

class Builder {

    function __construct() {

    }

    private function push($section, $element, &$updates){

        if(!array_key_exists($section, $updates))
            $updates[$section] = array();

        $key = 'root';

        if($section === 'files'){

            $key = hash('crc32b', ake($element, 'name'));

        }elseif($section === 'ns'){

            $key = strtolower(str_replace('\\', '_', ake($element, 'name', 'root')));

        }else{

            $key = strtolower(implode('_', array_merge(ake($element, 'namespace', array()), array(ake($element, 'name')))));

        }

        if(array_key_exists($key, $updates[$section]))
            $element = array_merge_recursive($updates[$section][$key], $element);

        return $updates[$section][$key] = $element;

    }

    public function scanDoc($path, &$data){

        if(!$path instanceof \Hazaar\File\Dir)
            $path = new \Hazaar\File\Dir($path);

        if(!$path->exists())
            return null;

        if(!is_array($data))
            $data = array();

        $mkdocs = $path->get('mkdocs.yml');

        //If mkdocs, we enter Markdown mode.
        if($mkdocs->exists()){

            try{

                $yaml = \Symfony\Component\Yaml\Yaml::parse($mkdocs->get_contents());

            }
            catch(\Exception $e){

                throw new \Exception('YAML parser error.  Is your mkdocs.yml file correct?');

            }

            if(array_key_exists('markdown_extensions', $yaml))
                $data['ext'] = $yaml['markdown_extensions'];

            $data['index'] = array(
                'docs' => array(
                    'title' => $yaml['site_name'],
                    'pages' => $this->processDocIndex($yaml['pages'])
                )
            );

            $docdir = $path->dir(ake($yaml, 'docs_dir', 'docs'));

            $docdir->setRelativePath(null);

            $this->processDocdir($data['docs']['pages'], $docdir);

        }

        return $data;

    }

    private function processDocIndex($pages){

        $index = array();

        foreach($pages as $page){

            if(!is_array($page))
                throw new \Exception('Invalid index item: ' . $page);

            foreach($page as $title => $item){

                $id = hash('crc32', 'doc_' . $title);

                if(is_array($item)){

                    $index[$id] = array(
                        'type' => 'sec',
                        'label' => $title,
                        'items' => $this->processDocIndex($item)
                    );

                }else{

                    $index[$id] = array(
                        'type' => 'doc',
                        'path' => $item,
                        'label' => $title
                    );

                }

            }

        }

        return $index;

    }

    private function processDocdir(&$data, \Hazaar\File\Dir $docdir){

        if(!is_array($data))
            $data = array();

        $files = $docdir->find('*');

        if(!count($files) > 0)
            return false;

        foreach($files as &$file){

            if(!$file instanceof \Hazaar\File || $file->is_dir() || substr($file->name(), 0, 1) === '.')
                continue;

            $this->processDocItem($data, $file, $docdir);

        }

        return $data;

    }

    private function processDocItem(&$data, \Hazaar\File $file, \Hazaar\File\Dir $docdir){

        if(!$file->exists())
            return false;

        $content = $file->get_contents();

        //If there is no \n, this could be a symlink ref on a Windows host.  Check the content to see if it points to a file.
        if(substr_count($content, "\n") === 0){

            if(!($file = $docdir->get($content)) instanceof \Hazaar\File)
                return false;

            if($file->exists())
                $content = $file->get_contents();

        }

        if(preg_match('/image\/(.+)/', $file->mime_content_type(),$matches)){

            $rel = $matches[0];

            $title = $file->basename();

            $content = base64_encode($content);

        }else{

            $rel = 'doc';

            $title = preg_match('/^\#\s(.*)$/m', $content, $matches) ? $matches[1] : $file->basename();

        }

        $name = $file->relativepath();

        $id = hash('crc32b', $name);

        $data[$id] = array(
            'rel' => $rel,
            'name' => $name,
            'title' => $title,
            'content' => $content
        );

        return true;

    }

    public function scanAPI($path, &$data) {

        if(!is_array($data))
            $data = array();

        if(PHP_OS_FAMILY === 'Windows')
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        $info = $this->searchPath($path, TRUE);

        foreach($info as &$file)
            $file['rel'] = str_replace($path . DIRECTORY_SEPARATOR, '', $file['source']);

        $updates = array();

        $this->processAPIDocumentation($info, $updates, $path);

        if(!count($updates) > 0)
            return false;

        $data['api'] = $updates;

        $data['index']['api'] = array(
            'title' => 'API Reference'
        );

        Builder::getAPINavPages($data['index']['api'], $updates);

        return true;

    }

    private function searchPath($path, $recurse = FALSE) {

        if(is_dir($path)) {

            $list = array();

            $dir = dir($path);

            while(($file = $dir->read()) !== FALSE) {

                if(substr($file, 0, 1) == '.')
                    continue;

                $filename = $path . '/' . $file;

                if($recurse && is_dir($filename)) {

                    $list = array_merge($list, $this->searchPath($filename, $recurse));

                } elseif(is_file($filename)) {

                    if($item = $this->parseItem($filename))
                        $list[] = $item;

                }

            }

            return $list;

        } elseif(is_file($path)) {

            return $this->parseItem($path);

        }

        return FALSE;

    }

    private function parseItem($filename) {

        $info = pathinfo($filename);

        if(strtolower(ake($info, 'extension')) === 'php') {

            $parser = new \Hazaar\Parser\PHP($filename, TRUE);

            return $parser->getInfo();

        }

        return FALSE;

    }

    private function processAPIDocumentation($info, &$updates, $path_prefix = null) {

        $namespaces = array();

        $interfaces = 0;

        $classes = 0;

        $functions = 0;

        foreach($info as $file) {

            /** PROCESS FILES */
            $this->insertFileRecord($file, $updates);

            /** PROCESS FUNCTIONS */
            foreach($file['functions'] as $function) {

                if(!(array_key_exists('name', $function) && $function['name']))
                    continue;

                if(!array_key_exists('namespaces', $function)
                    && (! array_key_exists('namespaces', $file) || ! in_array(NULL, $file['namespaces'])))
                    $file['namespaces'][] = NULL;

                $function['source'] = $file['rel'];

                $functions++;

                try{

                    $this->push('func', $function, $updates);

                }
                catch(\Exception $e){

                    continue;

                }

            }

            /** PROCESS INTERFACES */
            foreach($file['interfaces'] as $interface) {

                if(! array_key_exists('namespaces', $interface) &&
                   (! array_key_exists('namespaces', $file) || ! in_array(NULL, $file['namespaces']))
                )
                    $file['namespaces'][] = NULL;

                $interface['source'] = $file['rel'];

                $interfaces++;

                try{

                    $this->push('int', $interface, $updates);

                }
                catch(\Exception $e){

                    continue;

                }

            }

            /** PROCESS CLASSES */
            foreach($file['classes'] as $class) {

                if(! array_key_exists('namespaces', $class) &&
                   (! array_key_exists('namespaces', $file) || ! in_array(NULL, $file['namespaces']))
                )
                    $file['namespaces'][] = NULL;

                //Clean up the class property values.  These can cause problems if the keys have dots in them.
                if(array_key_exists('properties', $class) && is_array($class['properties'])){

                    foreach($class['properties'] as &$prop){

                        if(!array_key_exists('value', $prop))
                            continue;

                        if(is_array($prop['value']))
                            $prop['value'] = array();

                    }

                }

                $class['source'] = $file['rel'];

                $classes++;

                try{

                    $this->push('class', $class, $updates);

                }
                catch(\Exception $e){

                    continue;

                }

            }

            /** PROCESS NAMESPACES */
            if(! array_key_exists('namespaces', $file) || count($file['namespaces']) == 0)
                $file['namespaces'] = array(NULL);

            foreach($file['namespaces'] as $ns) {

                $name = NULL;

                if($ns === null)
                    continue;

                if($ns) {

                    $name = implode('\\', $ns['name']);

                    $namespaces[$name]['name'] = $name;

                    if(array_key_exists('comment', $ns)) {

                        $namespaces[$name]['defined_in'] = str_replace(LIBRARY_PATH . '/', '', $file['source']);

                        $namespaces[$name]['comment'] = $ns['comment'];

                        $namespaces[$name]['source'] = $file['rel'];

                    }

                }

                foreach($file['interfaces'] as $interface) {

                    if(!(array_key_exists('name', $interface) && $interface['name']))
                        continue;

                    $namespace = (array_key_exists('namespace', $interface) ? $interface['namespace'] : NULL);

                    if($ns['name'] === $namespace) {

                        $namespaces[$name]['interfaces'][] = array(
                            'name' => $interface['name'],
                            'desc' => (isset($interface['comment']['brief']) ? $interface['comment']['brief'] : NULL)
                        );

                    }

                }

                foreach($file['classes'] as $class) {

                    if(!(array_key_exists('name', $class) && $class['name']))
                        continue;

                    $namespace = (array_key_exists('namespace', $class) ? $class['namespace'] : NULL);

                    if($ns['name'] === $namespace) {

                        $namespaces[$name]['classes'][] = array(
                            'name' => $class['name'],
                            'desc' => (isset($class['comment']['brief']) ? $class['comment']['brief'] : NULL)
                        );

                    }

                }

                foreach($file['functions'] as $function) {

                    if(!(array_key_exists('name', $function) && $function['name']))
                        continue;

                    $namespace = (array_key_exists('namespace', $function) ? $function['namespace'] : NULL);

                    if($ns['name'] === $namespace) {

                        $function['source'] = $file['rel'];

                        $function['desc'] = (isset($function['comment']['brief']) ? $function['comment']['brief'] : NULL);

                        $namespaces[$name]['functions'][] = $function;

                    }

                }

                $parent = ($ns ? array_splice($ns['name'], 0, count($ns['name']) - 1) : array());

                $parent_name = ((count($parent) > 0) ? implode('\\', $parent) : NULL);

                $shortname = ($ns ? implode('\\', array_diff($ns['name'], $parent)) : NULL);

                if($shortname && (! array_key_exists($parent_name, $namespaces) ||
                                  ! array_key_exists('namespaces', $namespaces[$parent_name]) ||
                                  ! array_key_exists($shortname, $namespaces[$parent_name]['namespaces']))
                ) {

                    $namespaces[$parent_name]['namespaces'][$shortname] = array(
                        'name' => $shortname,
                        'desc' => (isset($ns['comment']['brief']) ? $ns['comment']['brief'] : NULL)
                    );

                }

            }
            /** END NAMESPACES */
        }

        foreach($namespaces as $ns)
            $this->push('ns', $ns, $updates);

        $results = array(
            'files'      => count($info),
            'interfaces' => $interfaces,
            'classes'    => $classes,
            'functions'  => $functions,
            'namespaces' => count($namespaces),
            'mem'        => array(
                'current' => str_bytes(memory_get_usage()),
                'peak'    => str_bytes(memory_get_peak_usage())
            )
        );

        return $results;

    }

    private function insertFileRecord($file, &$updates) {

        $db_file = array(
            'name'   => $file['rel'],
            'size'   => $file['size'],
            'source' => utf8_encode(file_get_contents($file['source']))
        );

        if(count($file['namespaces']) > 0) {

            foreach($file['namespaces'] as $ns) {

                if(array_key_exists('comment', $ns)) {

                    $db_file['namespaces'][] = $ns['name'];

                }

            }

        }

        if(count($file['interfaces']) > 0) {

            $db_file['interfaces'] = array();

            foreach($file['interfaces'] as $interface)
                $db_file['interfaces'][] = $interface['name'];

        }

        if(count($file['classes']) > 0) {

            $db_file['classes'] = array();

            foreach($file['classes'] as $class)
                $db_file['classes'][] = $class['name'];

        }

        if(count($file['functions']) > 0) {

            $db_file['functions'] = array();

            foreach($file['functions'] as $function){

                if(!array_key_exists('name', $function))
                    continue; //No name?  Probably a closure.

                $db_file['functions'][] = $function['name'];

            }

        }

        if(array_key_exists('comment', $file))
            $db_file['comment'] = $file['comment'];

        return $this->push('files', $db_file, $updates);

    }

    static private function getAPINavPages(&$index, $api){

        $root = array();

        $namespaces =& $api['ns'];

        uasort($namespaces, function ($a, $b) {
            if(!array_key_exists('name', $a)) return -1;
            if(!array_key_exists('name', $b)) return 1;
            return ((ake($a, 'name', 'root') > ake($b, 'name', 'root')) ? 1 : -1);
        });

        foreach($namespaces as $ns) {

            if(!(array_key_exists('name', $ns) && $ns['name']))
                continue;

            if(($pos = strrpos($ns['name'], '\\')) > 0)
                $pos++;

            $obj = array(
                'type'  => 'ns',
                'path'    => 'ns/' . str_replace('\\', '/', $ns['name']),
                'label' => substr($ns['name'], $pos)
            );

            $parent =& $root;

            if($parent_name = substr($ns['name'], 0, strrpos($ns['name'], '\\'))){

                $parts = explode('\\', $parent_name);

                foreach($parts as $part){

                    $part_id = hash('crc32b', 'ns_' . $part);

                    if(!array_key_exists('items', $parent))
                        $parent['items'] = array();

                    if(!array_key_exists($part_id, $parent['items']))
                        $parent['items'][$part_id] = array('type' => 'ns', 'label' => $part, 'items' => array());

                    $parent =& $parent['items'][$part_id];

                }

            }

            $name = hash('crc32b', 'ns_' . $obj['label']);

            $parent['items'][$name] = $obj;

        }

        $types = array('int' => 'interface', 'class' => 'class');

        foreach($types as $type => $name){

            if(!($pages =& $api[$type]))
                continue;

            uasort($pages, function ($a, $b) {
                return (($a['name'] > $b['name']) ? 1 : -1);
            });

            foreach($pages as $page) {

                if(is_array($page['name'])){
                    continue;

                    //TODO:  Need to get this warning back to the user.
                    $msg = "The class '{$page['name'][0]}' is defined in multiple source files:\n";

                    foreach($page['source'] as $source)
                        $msg .= "\t$source\n";

                    throw new \Exception($msg, E_USER_WARNING);

                }

                $obj = array(
                    'type'   => $name,
                    'label'  => $page['name'],
                    'path'   => $type . '/' . implode('/', ake($page, 'namespace', array())) . '/' . $page['name'],
                    'prefix' => '<i class="icon icon-' . $name . '"></i>'
                );

                $parent =& $root;

                if(array_key_exists('namespace', $page)) {

                    foreach($page['namespace'] as $part){

                        $part_id = hash('crc32b', 'ns_' . $part);

                        if(!array_key_exists('items', $parent))
                            $parent['items'] = array();

                        if(!array_key_exists($part_id, $parent['items']))
                            throw new \Exception('Namespace ' . $part . ' is not defined!');

                        $parent =& $parent['items'][$part_id];

                    }

                }

                $id = hash('crc32b', $type . '_' . $obj['label']);

                $parent['items'][$id] = $obj;

            }

        }

        $index['pages'] = $root['items'];

        return true;

    }

}
