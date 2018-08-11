<?php

namespace Hazaar\Template;

/**
 * Smarty 2.0 Templates
 *
 * This class implements the entire Smarty 2.0 template specification.  For documentation on the
 * Smarty 2.0 template format see the Smarty 2.0 online documentation: https://www.smarty.net/docsv2/en/
 *
 * Tags are in the format of {$tagname}.  This tag would reference a parameter passed to the parser
 * with the array key value of 'tagname'.  Such as:
 *
 * ```
 * $tpl = new \Hazaar\Template\Smarty($template_content);
 * $tpl->render(array('tagname' => 'Hello, World!'));
 * ```
 *
 */
class Smarty {

    static protected $tags = array(
        'if',
        'elseif',
        'else',
        'section',
        'sectionelse',
        'url',
        'foreach',
        'foreachelse',
        'ldelim',
        'rdelim'
    );

    static private $modifiers = array('date_format', 'capitalize');

    protected $__content = null;

    protected $__compiled_content = null;

    private $__section_stack = array();

    private $__foreach_stack = array();

    public $ldelim = '{';

    public $rdelim = '}';

    public $allow_globals = true;

    function __construct($content){

        $this->loadFromString($content);

    }

    public function loadFromString($content) {

        $this->__content = (string)$content;

        $this->__compiled_content = null;

    }

    public function render($params = array()) {

        $app = \Hazaar\Application::getInstance();

        $default_params = array(
            'hazaar' => array('version' => HAZAAR_VERSION),
            'smarty' => array(
                'now' => new \Hazaar\Date(),
                'const' => get_defined_constants(),
                'capture' => null,
                'config' => $app->config->toArray(),
                'section' => array(),
                'foreach' => array(),
                'template' => null,
                'version' => 2,
                'ldelim' => $this->ldelim,
                'rdelim' => $this->rdelim
            )
        );

        if($this->allow_globals){

            $default_params['_COOKIE'] = $_COOKIE;

            $default_params['_ENV'] = $_ENV;

            $default_params['_GET'] = $_GET;

            $default_params['_POST'] = $_POST;

            $default_params['_REQUEST'] = $_REQUEST;

            $default_params['_SERVER'] = $_SERVER;

        }

        $params = array_merge($default_params, (array)$params);

        $id = '_template_' . md5(uniqid());

        if(!$this->__compiled_content)
            $this->__compiled_content = $this->compile();

        $code = "class $id {

            private \$modify;

            private \$variables = array();

            function __construct(){ \$this->modify = new \Hazaar\Template\Smarty\Modifier; }

            public function render(\$params){

                extract(\$params);

                ?>{$this->__compiled_content}<?php

            }

            private function url(\$path = null){ return new \Hazaar\Application\Url(urldecode(\$path)); }

        }";

        eval($code);

        $obj = new $id;

        ob_start();

        $obj->render($params);

        return ob_get_clean();

    }

    private function setType($value, $type = 'string', $args = null){

        switch($type){

            case 'date':

                if(!$value instanceof \Hazaar\Date)
                    $value = new \Hazaar\Date($value);

                $value = ($args?$value->format($args):(string)$value);

                break;

            case 'string':
            default:

                $value = (string) $value;

        }

        return $value;

    }

    protected function parsePARAMS($params){

        $parts = preg_split('/\s+/', $params);

        $params = array();

        foreach($parts as $part)
            $params = array_merge($params, array_unflatten($part));

        return $params;

    }

    public function compile(){

        $compiled_content = preg_replace(array('/\<\?/', '/\?\>/'), array('&lt;?','?&gt;'), $this->__content);

        while(preg_match_all('/\{([#\$][^\}]+|(\/?\w+)\s*([^\}]*))\}(\r?\n)?/', $compiled_content, $matches, PREG_PATTERN_ORDER)){

            foreach($matches[0] as $idx => $match){

                $replacement = '';

                //It matched a variable
                if(substr($matches[1][$idx], 0, 1) === '$'){

                    $replacement = $this->replaceVAR($matches[1][$idx]);

                    //Matched a config variable
                }elseif(substr($matches[1][$idx], 0, 1) === '#' && substr($matches[1][$idx], -1) === '#'){

                    $replacement = $this->replaceCONFIG_VAR(substr($matches[1][$idx], 1, -1));

                    //Must be a function so we exec the internal function handler
                }elseif((substr($matches[2][$idx], 0, 1) == '/'
                    && in_array(substr($matches[2][$idx], 1), Smarty::$tags))
                    || in_array($matches[2][$idx], Smarty::$tags)){

                    $func = 'compile' . str_replace('/', 'END', strtoupper($matches[2][$idx]));

                    $replacement = $this->$func($matches[3][$idx]);

                }

                if($matches[4][$idx])
                    $replacement .= " \r\n";

                $compiled_content = preg_replace('/' . preg_quote($match, '/') . '/', $replacement, $compiled_content, 1);

            }

        }

        return $compiled_content;

    }

    protected function compileVAR($name){

        $modifiers = array();

        if($pos = strpos($name, '|')){

            $c_part = '';

            $quote = null;

            for($i = 0; $i<strlen($name); $i++){

                if($name[$i] === '|' && $quote === null){

                    $modifiers[] = $c_part;

                    $c_part = '';

                    continue;

                }elseif($name[$i] === '"' || $name[$i] == "'"){

                    $quote = ($quote == $name[$i]) ? null : $name[$i];

                }

                $c_part .= $name[$i];

            }

            $modifiers[] = $c_part;

            $name = array_shift($modifiers);

        }

        $parts = preg_split('/(\.|->|\[)/', $name, -1, PREG_SPLIT_DELIM_CAPTURE);

        $name = array_shift($parts);

        if(count($parts) > 0){

            foreach($parts as $idx => $part){

                if(!$part || $part == '.' || $part == '->' || $part == '[') continue;

                if(ake($parts, $idx-1) == '->')
                    $name .= '->' . $part;
                elseif(substr($part, 0, 1) == '$')
                    $name .= "[$part]";
                elseif(substr($part, -1) == ']'){
                    if(substr($part, 0, 1) == "'" && substr($part, -2, 1) == substr($part, 0, 1))
                        $name .= '[' . $part;
                    else
                        $name .= '[$smarty[\'section\'][\'' . substr($part, 0, -1) . "']['index']]";
                }else
                    $name .= "['$part']";

            }

        }

        if(count($modifiers) > 0){

            foreach($modifiers as $modifier){

                $params = explode(':', $modifier);

                $func = array_shift($params);

                if(Smarty\Modifier::has_function($func))
                    $name = '$this->modify->' . $func . '(' . $name . ((count($params) > 0) ? ', ' . implode(', ', $params) : '') . ')';

            }

        }

        return $name;

    }

    protected function compileVARS($string){

        if(preg_match_all('/\$[\w\.\[\]]+/', $string, $matches)){

            foreach($matches[0] as $match)
                $string = str_replace($match, '\' . ' . $this->compileVAR($match) . ' . \'', $string);

        }

        return $string;

    }

    protected function replaceVAR($name){

        $var = $this->compileVAR($name);

        return '<?php echo @(is_array(' . $var . ') ? print_r(' . $var . ', true) : ' . $var . ');?>';

    }

    protected function replaceCONFIG_VAR($name){

        return $this->replaceVAR("\$this->variables['$name']");

    }

    protected function compilePARAMS($params){

        if(preg_match_all('/\$\w[\w\.\$]+/', $params, $matches)){

            foreach($matches[0] as $match)
                $params = str_replace($match, $this->compileVAR($match), $params);

        }

        return $params;

    }

    private function compileIF($params){

        return '<?php if(@' . $this->compilePARAMS($params) . '): ?>';

    }

    private function compileELSEIF($params){

        return '<?php elseif(@' . $this->compilePARAMS($params) . '): ?>';

    }

    private function compileELSE($params){

        return '<?php else: ?>';

    }

    private function compileENDIF($tag){

        return '<?php endif; ?>';

    }

    private function compileSECTION($params){

        $parts = preg_split('/\s+/', $params);

        $params = array();

        foreach($parts as $part)
            $params += array_unflatten($part);

        //Make sure we have the name and loop required parameters.
        if(!(($name = ake($params, 'name')) && ($loop = ake($params, 'loop'))))
            return '';

        $this->__section_stack[] = array('name' => $name, 'else' => false);

        $var = $this->compileVAR($loop);

        $index = '$smarty[\'section\'][\'' . $name . '\'][\'index\']';

        $count = '$__count_' . $name;

        $code = "<?php \$smarty['section']['$name'] = []; if(isset($var) && is_array($var) && count($var)>0): ";

        $code .= "for($count=1, $index=" . ake($params, 'start', 0) . '; ';

        $code .= "$index<" . (is_numeric($loop) ? $loop : 'count(' . $this->compileVAR($loop) . ')') . '; ';

        $code .= "$count++, $index+=" . ake($params, 'step', 1) . '): ';

        if($max = ake($params, 'max'))
            $code .= 'if(' . $count . '>' . $max . ') break; ';

        $code .= "?>";

        return $code;

    }

    private function compileSECTIONELSE($tag){

        end($this->__section_stack);

        $this->__section_stack[key($this->__section_stack)]['else'] = true;

        return '<?php endfor; else: ?>';

    }

    private function compileENDSECTION($tag){

        $section = array_pop($this->__section_stack);

        if($section['else'] === true)
            return '<?php endif; ?>';

        return '<?php endfor; endif; array_pop($smarty[\'section\']); ?>';

    }

    private function compileURL($tag){

        return '<?php echo @$this->url(\'' .$this->compileVARS(trim($tag, "'")) . '\');?>';

    }

    public function compileFOREACH($params){

        $params = $this->parsePARAMS($params);

        $code = '';

        //Make sure we have the name and loop required parameters.
        if(($from = ake($params, 'from')) && ($item = ake($params, 'item'))){

            $name = ake($params, 'name', 'foreach_' . uniqid());

            $var = $this->compileVAR($from);

            $this->__foreach_stack[] = array('name' => $name, 'else' => false);

            $target = (($key = ake($params, 'key')) ? '$' . $key . ' => ' : '' ) . '$' . $item;

            $code = "<?php \$smarty['foreach']['$name'] = ['index' => -1, 'total' => count($var)]; ";

            $code .= "if(isset($var) && is_array($var) && count($var)>0): ";

            $code .= "foreach($var as $target): \$smarty['foreach']['$name']['index']++; ?>";

        }elseif(ake($params, 1) === 'as'){ //Smarty 3 support

            $name = ake($params, 'name', 'foreach_' . uniqid());

            $var = $this->compileVAR(ake($params, 0));

            $target = $this->compileVAR(ake($params, 2));

            $this->__foreach_stack[] = array('name' => $name, 'else' => false);

            $code = "<?php \$smarty['foreach']['$name'] = ['index' => -1, 'total' => count($var)]; ";

            $code .= "if(isset($var) && is_array($var) && count($var)>0): ";

            $code .= "foreach($var as $target): \$smarty['foreach']['$name']['index']++; ?>";

        }

        return $code;

    }

    public function compileFOREACHELSE($tag){

        end($this->__foreach_stack);

        $this->__foreach_stack[key($this->__foreach_stack)]['else'] = true;

        return '<?php endforeach; else: ?>';

    }

    public function compileENDFOREACH($tag){

        $loop = array_pop($this->__foreach_stack);

        if($loop['else'] === true)
            return '<?php endif; ?>';

        return '<?php endforeach; endif; ?>';

    }

    public function compileLDELIM($tag){

        return $this->ldelim;

    }

    public function compileRDELIM($tag){

        return $this->rdelim;

    }

}
