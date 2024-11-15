<?php

namespace Application\Model\Common\APIDoc;

class Renderer {

    private $page;

    private $html;

    private $markdown;

    private $allowCodeView = false;

    private $php_types = array(
        'bool'      => 'http://php.net/manual/en/language.types.boolean.php',
        'boolean'   => 'http://php.net/manual/en/language.types.boolean.php',
        'integer'   => 'http://php.net/manual/en/language.types.integer.php',
        'int'       => 'http://php.net/manual/en/language.types.integer.php',
        'double'    => 'http://php.net/manual/en/language.types.float.php',
        'float'     => 'http://php.net/manual/en/language.types.float.php',
        'string'    => 'http://php.net/manual/en/language.types.string.php',
        'array'     => 'http://php.net/manual/en/language.types.array.php',
        'object'    => 'http://php.net/manual/en/language.types.object.php',
        'resource'  => 'http://php.net/manual/en/language.types.resource.php',
        'null'      => 'http://php.net/manual/en/language.types.null.php',
        'mixed'     => 'http://php.net/manual/en/language.pseudo-types.php',
        'number'    => 'http://php.net/manual/en/language.pseudo-types.php',
        'callback'  => 'http://php.net/manual/en/language.pseudo-types.php',
        'void'      => 'http://php.net/manual/en/language.pseudo-types.php',
        '...'       =>   'http://php.net/manual/en/language.pseudo-types.php'
    );

    function __construct(\Application\Model\Common\Page $page, $ext = array(), $allowCodeView = false){

        $this->page = $page;

        $this->html = new \Hazaar\View\Helper\Html();

        $this->markdown = new \Hazaar\Parser\Markdown($ext);

    }

    public function render(){

        switch($this->page->type){

            case 'doc':

                if(substr($this->page['rel'], 0, 5) === 'image')
                    return $this->page->item[0];

                return $this->renderDocument($this->page->item[0]);

            case 'int':

                return $this->renderClass($this->page->item);

            case 'class':

                return $this->renderClass($this->page->item);

            case 'ns':

                return $this->renderNamespace($this->page->item);

            case 'func':

                return $this->renderFunction($this->page->item);

            case 404:

                return $this->html->h1('Page not found');

        }

        return 'Unknown document type: ' . $this->page->type;

    }

    public function strReplaceLinks(&$text) {

        if(!is_string($text))
            return $text;

        /*
         * Search for Markdown links and fix up the URL if they are not absolute
         *
         * Example: [the label](mypage.md) -> [the label](http://localhost/public/docs/mypage.md)
         *
         */
        $text = preg_replace_callback('/\[([^\[\]]+)\]\(([\w\-\.\\/]+)\)/U', function ($matches) {

            $link = $matches[2];

            $link = (substr($link, 0, 1) === '/')
                ? ltrim($link, '/')
                : (($this->page->path) ? dirname($this->page->path) . '/' : '')
                . $link;

            return '[' . $matches[1] . '](' . $this->url($link) . ')';

        }, $text);

        /*
         * Match any namespace, classes or interfaces and replace with API links
         *
         * Format: [[Namespace\Class[::func([$param])]]]
         */
        $text = preg_replace_callback('/\[\[([\w\\\\]+[\(\$\w\'\,\s\)]*)(\:\:(([^\]\(\)]+)(\([^\)]*\))?)?)?\]\]/', function ($matches) {

            if(!($info = $this->matchObject($matches[1])) !== false)
                return $matches[0];

            $url = $this->url('api', $info['path']);

            $a = new \Hazaar\Html\A($url, $matches[1]);

            if(count($matches) < 3)
                return $a;

            if($matches[3][0] === '$')
                $a .=  '::' . new \Hazaar\Html\A($url . '#prop_' . $matches[3], $matches[3]);
            elseif(!isset($matches[5]))
                $a .=  '::' . new \Hazaar\Html\A($url . '#const_' . $matches[4], $matches[4]);
            else
                $a .= '::' . new \Hazaar\Html\A($url . '#func_' . $matches[4], $matches[4]) . $matches[5];

            return $a;

        }, $text);

        return $text;

    }

    private function matchObject(&$object){

        if(!array_key_exists('api', $this->page->index))
            return false;

        if(substr($object, 0, 1) === '\\')
            $object = substr($object, 1);

        if(preg_match('/(\w+)\(.*\)/', $object, $matches))
            return array('path' => 'func/' . $matches[1], 'label' => $object);

        $parts = explode('\\', $object);

        $node = array('items' => $this->page->index['api']['pages']);

        end($parts);

        $last = key($parts);

        foreach($parts as $index => $part){

            $id = hash('crc32b', (($index === $last) ? 'class' : 'ns') . '_' . $part);

            if(!array_key_exists($id, $node['items']))
                return false;

            $node =& $node['items'][$id];

        }

        return $node;

    }

    public function url(){

        $args = func_get_args();

        array_unshift($args, $this->page->version);

        return new \Hazaar\Application\Url($this->page->project->name, implode('/', $args));

    }

    private function getAPILink($item, $hint = NULL, $ns = NULL, $append = NULL, $label = NULL) {

        if(preg_match('/([\w|\\\]+)(\W*)/', $item, $matches))
            $type = $matches[1];
        else
            $type = $item;

        if(array_key_exists($type, $this->php_types))
            return $this->html->a($this->php_types[$type] . $append, $item);

        if(substr($type, 0, 1) !== '\\') {

            if($ns) {

                if(!is_array($ns)) $ns = iterator_to_array($ns);

                $type = '\\' . implode('\\', $ns) . '\\' . $type;

            } else {

                $type = '\\' . $type;

            }

        }

        $page = ($hint ? strtolower($hint) : '') . str_replace('\\', '/', $type);

        $url = $this->url('api', $page);

        return $this->html->a($url . $append, ($label ? $label : $type));

    }

    private function renderDocument($doc){

        //Replace any markdown code chunks with GeSHi rendered code blocks
        $doc = preg_replace_callback('/(\<code(\s+class="(\w*)")\>|```(\w*))\r?\n(.*)\r?\n(\<\/code\>|```)/imsU', function($matches){
            $geshi = new \GeSHi($matches[5], ake($matches, 3, ake($matches, 4), 'php', true));
            return $geshi->parse_code();
        }, $doc);

        // Replace any wiki links with textile link markup to the docs action.
        $this->strReplaceLinks($doc);

        return $this->markdown->parse($doc);

    }

    private function renderNamespace($item = null){

        $div = $this->html->div()->class('api');

        $div->add($this->html->link('http://fonts.googleapis.com/css?family=Source+Sans+Pro', 'stylesheet', 'text/css'));

        if($this->allowCodeView === true && ($source = ake($item, 'source'))){

            $div->add($this->html->div($this->html->button($this->fontawesome->icon('code'))
                  ->class('btn-code')
                  ->attr('data-source', $source)
                  ->attr('data-line', ake($item, 'line'))
            )->class('doc-page-code'));

        }

        $div->add($this->html->div(ake($item, 'name'))->class('doc-ns-hdr'));

        if($namespace = ake($item, 'name'))
            $namespace = explode('\\', $namespace);

        if($comment = ake($item, 'comment'))
            $div->add($this->renderComment($comment, false));

        if($namespaces = ake($item, 'namespaces')){

            uasort($namespaces, function ($a, $b) {
                return (($a['name'] > $b['name']) ? 1 : -1);
            });

            $div->add($this->html->div('Namespaces')->class('doc-page-section'));

            $table = $this->html->table()->appendTo($div)->class('api-ns-layout');

            foreach($namespaces as $ns){

                $table->add($this->html->tr(array(
                    $this->html->td($this->getAPILink($ns['name'], 'ns', $namespace, NULL, $ns['name'])),
                    $this->html->td($ns['desc'])
                )));

            }

        }

        if($interfaces = ake($item, 'interfaces')){

            uasort($interfaces, function ($a, $b) {
                return (($a['name'] > $b['name']) ? 1 : -1);
            });

            $div->add($this->html->div('Interfaces')->class('doc-page-section'));

            $table = $this->html->table()->appendTo($div)->class('api-ns-layout');

            foreach($interfaces as $interface){

                $table->add($this->html->tr(array(
                    $this->html->td($this->getAPILink($interface['name'], 'int', $namespace, NULL, $interface['name'])),
                    $this->html->td($interface['desc'])
                )));

            }

        }

        if($classes = ake($item, 'classes')){

            uasort($classes, function ($a, $b) {
                return (($a['name'] > $b['name']) ? 1 : -1);
            });

            $div->add($this->html->div('Classes')->class('doc-page-section'));

            $table = $this->html->table()->appendTo($div)->class('api-ns-layout');

            foreach($classes as $class){

                $table->add($this->html->tr(array(
                    $this->html->td($this->getAPILink($class['name'], 'class', $namespace, NULL, $class['name'])),
                    $this->html->td($class['desc'])
                )));

            }

        }

        if($functions = ake($item, 'functions')){

            uasort($functions, function ($a, $b) {
                return (($a['name'] > $b['name']) ? 1 : -1);
            });

            $div->add($this->html->div('Functions')->class('doc-page-section'));

            $table = $this->html->table()->appendTo($div)->class('api-ns-layout');

            foreach($functions as $func){

                $table->add($this->html->tr(array(
                    $this->html->td($this->getAPILink($func['name'], 'func', $namespace, NULL, $func['name'])),
                    $this->html->td($func['desc'])
                )));

            }

        }

        return $div;

    }

    private function renderClass($class){

        if($namespace = ake($class, 'namespace'))
            $namespace = implode('\\', $namespace);

        $div = $this->html->div(array(
            $this->html->div($namespace)->class('doc-ns-hdr'),
            $this->html->div($class['name'])->class('doc-page-hdr')
        ))->class('api');

        $labels = $this->html->div()->class('api-tags')->appendTo($div);

        if(ake($class, 'abstract') === true)
            $labels->add($this->html->span('Abstract')->class('label label-warning'));

        if($comment = ake($class, 'comment'))
            $div->add($this->renderComment($comment));

        if($extends = ake($class, 'extends')) {

            $this->html->div(array(
                $this->html->h5('Extends'),
                $this->getAPILink($extends, 'class')
            ))->class('doc-page-content')->appendTo($div);

        }

        if($implements = ake($class, 'implements')) {

            $i = $this->html->div($this->html->h5('Implements'))->class('doc-page-content')->appendTo($div);

            foreach($implements as $interface)
                $i->add($this->html->div($this->getAPILink($interface, 'int', $this->namespace)));

        }

        $div->add($this->html->div('Summary')->class('doc-page-section'));

        $this->html->table(array(
            $this->html->tr(array(
                $this->html->th('Methods'),
                $this->html->th('Properties'),
                $this->html->th('Constants')
            )),
            $this->html->tr(array(
                $methodsTD = $this->html->td(),
                $propTD = $this->html->td(),
                $constTD = $this->html->td()
            ))
        ))->class('table')->appendTo($div);

        if($constants = ake($class, 'constants')){

            $this->html->div('Constants')->class('doc-page-section')->appendTo($div);

            foreach($constants as $constant) {

                $constTD->add($this->html->div($this->html->a('#const_' . $constant['name'], $constant['name'])));

                $constant['namespace'] = $this->namespace;

                if(!array_key_exists('source', $constant))
                    $constant['source'] = ake($class, 'source');

                $div->add($this->renderConstant($constant));

            }

        }else $constTD->add($this->html->i('No constants')->class('small'));

        if($properties = ake($class, 'properties')){

            $this->html->div('Properties')->class('doc-page-section')->appendTo($div);

            foreach($properties as $property) {

                $propTD->add($this->html->div($this->html->a('#prop_' . $property['name'], $property['name'])));

                $property['namespace'] = $this->namespace;

                if(!array_key_exists('source', $property))
                    $property['source'] = ake($class, 'source');

                $div->add($this->renderProperty($property));

            }

        }else $propTD->add($this->html->i('No properties')->class('small'));

        if($methods = ake($class, 'methods')){

            $this->html->div('Methods')->class('doc-page-section')->appendTo($div);

            foreach($methods as $method) {

                $methodsTD->add($this->html->div($this->html->a('#func_' . $method['name'], $method['name'])));

                $method['namespace'] = $this->namespace;

                if(! array_key_exists('source', $method))
                    $method['source'] = $this->source;

                $div->add($this->renderMethod($method));

            }

        }else $methodsTD->add($this->html->i('No methods')->class('small'));

        return $div;

    }

    private function renderComment(&$comment = null, $showTags = false){

        $content = array();

        if($brief = ake($comment, 'brief'))
            $content[] = $this->html->div($brief)->class('doc-page-brief');

        if($detail = ake($comment, 'detail'))
            $content[] = $this->html->div($this->renderDocument($detail))->class('doc-page-detail');

        if($tags = ake($comment, 'tags')) {

            if($code = ake($tags, 'code')){

                foreach($code as $source) {

                    $div = $this->html->div($this->html->h4('Example'))->class('doc-page-content');

                    $div->add($this->html->code($source));

                    $content[] = $div;

                }

            }

            $types = array('info', 'warning', 'danger');

            foreach($types as $type) {

                if(isset($tags[$type])) {

                    foreach($tags[$type] as $msg)
                        $content[] = $this->html->div($msg)->class('alert alert-' . $type);

                }

            }

            if($showTags !== false)
                $content[] = $this->renderTags($tags);

        }

        return $content;

    }

    private function renderFunction($function){

        $div = $this->html->div()->class('api');

        if($this->allowCodeView === true)
            $div->add($this->html->div($this->html->button($this->fontawesome->icon('code'))
                ->class('btn-code')
                ->attr('data-source', $this->source)
                ->attr('data-line', $this->line)
            )->class('doc-page-code'));

        if($namespace = ake($function, 'namespace'))
            $namespace = implode('\\', $this->namespace);

        $div->add(array(
            $this->html->div($namespace)->class('doc-ns-hdr'),
            $this->html->div($function['name'])->class('doc-page-hdr')
        ));

        $dec_params = array();

        if($params = ake($function, 'params')) {

            foreach($params as $param) {

                $value = '';

                if(array_key_exists('value', $param)) {

                    $value = ' = ';

                    switch(strtolower(gettype($param['value']))) {
                        case 'array' :
                            $value .= print_r($param['value'], true);
                            break;

                        case 'integer' :
                        case 'float' :
                            $value .= (string)$param['value'];
                            break;

                        case 'string' :
                            $value .= "'" . $param['value'] . "'";
                            break;

                        case 'boolean' :
                            $value .= strbool($param['value']);
                            break;

                        case 'null' :
                            $value .= ' null';
                            break;
                    }

                }

                $dec_params[] = $param['name'] . $value;

            }

        }

        $dec = ake($function, 'name') . '(' . implode(', ', $dec_params) . ')';

        if($return = ake($function, 'comment.tags.return'))
            $dec .= ' : ' . $return[0]['type'];

        $div->add($this->html->div($dec)->class('doc-member-def'));

        if($comment = ake($function, 'comment'))
            $div->add($this->renderComment($comment));

        $content = $this->html->div()->class('doc-page-content');

        /**
         * PARAMETERS
         */
        if($params = ake($function, 'params')) {

            $content->add($this->html->h4('Parameters'))->class('doc-page-content');

            $content->add($table = $this->html->table()->class('table'));

            foreach($params as $param) {

                $table->add($row = $this->html->tr());

                $row->add($this->html->td(ltrim($param['name'], '$'))->class('func-name'));

                $type = (isset($param['type'])?$param['type']:null);

                $desc = $this->html->i('No description')->class('small');

                if(array_key_exists('type', $param)){

                    if(!$type) $type = $param['type'];

                    if(isset($param['desc']))
                        $desc = $param['desc'];

                }

                if($type)
                    $type = $this->getAPILink($type, 'class', $this->namespace);

                $row->add(array(
                    $this->html->td($type)->class('func-type'),
                    $this->html->td($this->renderDocument($desc))->class('func-desc')
                ));

                if($row->count() < 3)
                    $row->add($this->html->td());

            }

        }

        /**
         * RETURNS
         */
        if($comment = ake($function, 'comment')) {

            if($return = ake($comment, 'tags.return')) {

                $content->add($this->html->h4('Returns'));

                $content->add($table = $this->html->table()->class('api-returns'));

                foreach($this->comment['tags']['return'] as $ret) {

                    $table->add($row = $this->html->tr($this->html->td($this->getAPILink($ret['type'], 'class', $this->namespace))));

                    $row->add($this->html->td((isset($ret['desc'])?$this->partial('page-content', array('content' => $ret['desc'])):null)));

                }


            }

        }

        $div->add($content);

        return $div;

    }

    private function renderConstant(&$constant){

        $content = array();

        $tags = $this->html->div()->class('api-tags')->appendTo($content);

        if(ake($constant, 'inherited') === true)
            $tags->add($this->html->span('Inherited')->class('label label-primary'));

        $this->html->a()->name('const_' . $constant['name'])->appendTo($content);

        $this->html->div($constant['name'])->class('doc-member-hdr')->appendTo($content);

        if($this->allowCodeView)
            $this->html->div($this->html->button($this->fontawesome->icon('code'))
              ->class('btn-code')
              ->attr('data-source', $this->source)
              ->attr('data-line', $this->line)
          )->class('doc-page-code')->appendTo($content);

        $def = $this->html->div($constant['name'])->class('doc-member-def')->appendTo($content);

        if($type = ake(ake($constant, 'comment.tags.var'), $constant['name'], 'type'))
            $def->add(' : ' . $type);

        if($comment = ake($constant, 'comment'))
            $content[] = $this->renderComment($comment);

        if($type) {

            $div = $this->html->div($this->html->h4('Type'))->class('doc-page-content')->appendTo($content);

            $div->add($type = $this->html->div($this->getAPILink($type, 'class', $this->namespace)));

            if($desc = ake(ake($constant, 'comment.tags.var'), $constant['name'], 'desc'))
                $type->add(' - ', $desc);

        }

        return $content;

    }

    private function renderProperty(&$property){

        $content = array();

        $tags = $this->html->div()->class('api-tags');

        if(ake($property, 'inherited') === true)
            $tags->add($this->html->span('Inherited')->class('label label-primary'));

        if(ake($property, 'static') === true)
            $tags->add($this->html->span('Static')->class('label label-danger'));

        $content[] = $tags;

        $content[] = $this->html->a()->name('prop_' . $property['name']);

        $content[] = $hdr = $this->html->div()->class('doc-member-hdr');

        if(!($type = ake($property, 'type')))
            $type = 'public';

        if($type == 'public')
            $hdr->add($this->html->i()->class('fa fa-check-square-o'));
        elseif($type == 'private')
            $hdr->add($this->html->i()->class('fa fa-lock'));
        elseif($type == 'protected')
            $hdr->add($this->html->i()->class('fa fa-shield'));

        $hdr->add($property['name']);

        $content[] = $def = $this->html->div($property['name'])->class('doc-member-def');


        if($type = ake(ake(ake($property, 'comment.tags.var'), $property['name']), 'type'))
            $def->add($type);

        if($comment = ake($property, 'comment'))
            $content[] = $this->renderComment($comment);

        if($type) {

            $div = $this->html->div($this->html->h4('Type'))->class('doc-page-content');

            $div->add($type = $this->html->div($this->getAPILink($type, 'class', $this->namespace)));

            if(isset($this->comment['tags']['var'][$this->get('name')]['desc']))
                $type->add(' - ', $this->comment['tags']['var'][$this->get('name')]['desc']);

            $content[] = $div;

        }

        return $content;

    }

    private function renderMethod(&$method){

        $content = array();

        $tags = $this->html->div()->class('api-tags')->appendTo($content);

        if(ake($method, 'inherited') === true)
            $tags->add($this->html->span('Inherited')->class('label label-primary'));

        if(ake($method, 'static') === true)
            $tags->add($this->html->span('Static')->class('label label-danger'));

        $this->html->a()->name('func_' . $method['name'])->appendTo($content);

        $hdr = $this->html->div()->class('doc-member-hdr')->appendTo($content);

        if(!($type = ake($method, 'type')))
            $type = 'public';

        if($type == 'public')
            $hdr->add($this->html->i()->class('fa fa-check-square-o'));
        elseif($this->type == 'private')
            $hdr->add($this->html->i()->class('fa fa-lock'));
        elseif($this->type == 'protected')
            $hdr->add($this->html->i()->class('fa fa-shield'));

        $hdr->add($method['name'] . '()');

        $dec_params = array();

        if($params = ake($method, 'params')) {

            foreach($params as $param) {

                $value = '';

                if(array_key_exists('value', $param)) {

                    $value = ' = ';

                    switch(strtolower(gettype($param['value']))) {
                        case 'array' :
                            $value .= print_r($param['value'], true);
                            break;

                        case 'integer' :
                        case 'float' :
                            $value .= (string)$param['value'];
                            break;

                        case 'string' :
                            $value .= "'" . $param['value'] . "'";
                            break;

                        case 'boolean' :
                            $value .= strbool($param['value']);
                            break;

                        case 'null' :
                            $value .= ' null';
                            break;
                    }

                }

                $dec_params[] = $param['name'] . $value;

            }

        }

        $dec = $method['name'] . '(' . implode(', ', $dec_params) . ')';

        if($return = ake($method, 'comment.tags.return'))
            $dec .= ' : ' . $return[0]['type'];

        $this->html->div($dec)->class('doc-member-def')->appendTo($content);

        if($comment = ake($method, 'comment'))
            $content[] = $this->renderComment($comment);

        $doc = $this->html->div()->class('doc-page-content');

        /**
         * PARAMETERS
         */
        if($params = ake($method, 'params')) {

            $doc->add($this->html->h4('Parameters'))->class('doc-page-content');

            $doc->add($table = $this->html->table()->class('table'));

            foreach($params as $param) {

                $table->add($row = $this->html->tr());

                $row->add($this->html->td($param['name'])->class('func-name'));

                $type = (isset($param['type'])?$param['type']:null);

                $desc = $this->html->i('No description')->class('small');

                $name = ltrim($param['name'], '$');

                if(array_key_exists('type', $param)){

                    if(!$type) $type = $this->comment['tags']['param'][$name]['type'];

                    if(array_key_exists('desc', $param))
                        $desc = $param['desc'];

                }

                if($type)
                    $type = $this->getAPILink($type, 'class', $this->namespace);

                $row->add(array(
                    $this->html->td($type)->class('func-type'),
                    $this->html->td($this->renderDocument($desc))->class('func-desc')
                ));

                if($row->count() < 3)
                    $row->add($this->html->td());

            }

        }

        /**
         * RETURNS
         */
        if($comment = ake($method, 'comment')) {

            if($return = ake($comment, 'tags.return')) {

                $doc->add($this->html->h4('Returns'));

                $doc->add($table = $this->html->table()->class('api-returns'));

                foreach($return as $ret) {

                    $table->add($row = $this->html->tr($this->html->td($this->getAPILink($ret['type'], 'class', $this->namespace))));

                    $row->add($this->html->td((isset($ret['desc'])?$this->renderDocument($ret['desc']):null)));

                }


            }

        }

        return $content;

    }

    private function renderTags(&$tags){

        $div = $this->html->div($this->html->h4('Tags'))->class('doc-page-content');

        $ignore = array(
            'param',
            'return',
            'code',
            'info',
            'warning',
            'danger',
            'var'
        );

        $div->add($table = $this->html->table()->class('table'));

        foreach($tags as $tag => $data) {

            if(in_array($tag, $ignore))
                continue;

            foreach($data as $item) {

                $table->add($row = $this->html->tr());

                $row->add($this->html->td(ucwords($tag)));

                if(is_array($item)) {

                    foreach($item as $i)
                        $row->add($this->html->td($this->renderDocument($i)));

                } else{

                    $row->add($this->html->td($this->renderDocument($item)));

                }

            }

        }

        return $div;

    }

}
