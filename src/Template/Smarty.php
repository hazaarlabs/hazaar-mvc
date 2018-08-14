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
        'rdelim',
        'capture',
        'assign',

        //Hybrid Smarty 3.0 Bits
        'function'
    );

    static private $modifiers = array('date_format', 'capitalize');

    protected $__content = null;

    protected $__compiled_content = null;

    protected $__custom_functions = array();

    private   $__custom_function_handler;

    private $__section_stack = array();

    private $__foreach_stack = array();

    private $__capture_stack = array();

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

    public function registerFunctionHandler($object){

        if(is_object($object))
            $this->__custom_function_handler = $object;

    }

    public function render($params = array()) {

        $app = \Hazaar\Application::getInstance();

        $default_params = array(
            'hazaar' => array('version' => HAZAAR_VERSION),
            'smarty' => array(
                'now' => new \Hazaar\Date(),
                'const' => get_defined_constants(),
                'capture' => array(),
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

            private \$functions = array();

            function __construct(){ \$this->modify = new \Hazaar\Template\Smarty\Modifier; }

            public function setCustomHandler(\$object){ \$this->custom_handler = \$object; }

            public function render(\$params){

                extract(\$params);

                ?>{$this->__compiled_content}<?php

            }

            private function url(\$path = null){ return new \Hazaar\Application\Url(urldecode(\$path)); }

        }";

        eval($code);

        $obj = new $id;

        ob_start();

        if($this->__custom_function_handler)
            $obj->setCustomHandler($this->__custom_function_handler);

        $obj->render($params);

        return ob_get_clean();

    }

    protected function setType($value, $type = 'string', $args = null){

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

        $regex = '/\{([#\$\*][^\}]+|(\/?\w+)\s*([^\}]*))\}(\r?\n)?/';

        $literal = false;

        $strip = false;

        $compiled_content = preg_replace_callback($regex, function($matches) use(&$literal, &$strip){

            $replacement = '';

            if(preg_match('/(\/?)literal/', $matches[1], $literals)){

                $literal = ($literals[1] !== '/');

            }elseif($literal){

                return $matches[0];

                //It matched a variable
            }elseif(substr($matches[1], 0, 1) === '$'){

                $replacement = $this->replaceVAR($matches[1]);

                //Matched a config variable
            }elseif(substr($matches[1], 0, 1) === '#' && substr($matches[1], -1) === '#'){

                $replacement = $this->replaceCONFIG_VAR(substr($matches[1], 1, -1));

                //Must be a function so we exec the internal function handler
            }elseif((substr($matches[2], 0, 1) == '/'
                && in_array(substr($matches[2], 1), Smarty::$tags))
                || in_array($matches[2], Smarty::$tags)){

                $func = 'compile' . str_replace('/', 'END', strtoupper($matches[2]));

                $replacement = $this->$func($matches[3]);

            }elseif(array_key_exists($matches[2], $this->__custom_functions)){

                $replacement = $this->compileCUSTOMFUNC($matches[2], $matches[3]);

            }elseif(is_object($this->__custom_function_handler) && method_exists($this->__custom_function_handler, $matches[2])){

                $replacement = $this->compileCUSTOMHANDLERFUNC($matches[2], $matches[3]);

            }elseif(preg_match('/(\/?)strip/', $matches[1], $flags)){

                $strip = ($flags[1] !== '/');

            }

            if($strip === true)
                $replacement = trim($replacement);
            elseif(isset($matches[4]))
                $replacement .= " \r\n";

            return $replacement;

        }, $compiled_content);

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

        if(is_array($params)){

            $out = array();

            foreach($params as $p)
                $out[] = $this->compilePARAMS($p);

            return implode(', ', $out);

        }

        if(is_string($params)){

            if(preg_match_all('/\$\w[\w\.\$]+/', $params, $matches)){

                foreach($matches[0] as $match)
                    $params = str_replace($match, $this->compileVAR($match), $params);

            }else $params = "'$params'";

        }elseif(is_int($params) || is_float($params)){

            $params = (string)$params;

        }elseif(is_bool($params)){

            $params = $params ? 'true' : 'false';

        }

        return $params;

    }

    protected function compileIF($params){

        return '<?php if(@' . $this->compilePARAMS($params) . '): ?>';

    }

    protected function compileELSEIF($params){

        return '<?php elseif(@' . $this->compilePARAMS($params) . '): ?>';

    }

    protected function compileELSE($params){

        return '<?php else: ?>';

    }

    protected function compileENDIF($tag){

        return '<?php endif; ?>';

    }

    protected function compileSECTION($params){

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

    protected function compileSECTIONELSE($tag){

        end($this->__section_stack);

        $this->__section_stack[key($this->__section_stack)]['else'] = true;

        return '<?php endfor; else: ?>';

    }

    protected function compileENDSECTION($tag){

        $section = array_pop($this->__section_stack);

        if($section['else'] === true)
            return '<?php endif; ?>';

        return '<?php endfor; endif; array_pop($smarty[\'section\']); ?>';

    }

    protected function compileURL($tag){

        return '<?php echo @$this->url(\'' .$this->compileVARS(trim($tag, "'")) . '\');?>';

    }

    protected function compileFOREACH($params){

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

    protected function compileFOREACHELSE($tag){

        end($this->__foreach_stack);

        $this->__foreach_stack[key($this->__foreach_stack)]['else'] = true;

        return '<?php endforeach; else: ?>';

    }

    protected function compileENDFOREACH($tag){

        $loop = array_pop($this->__foreach_stack);

        if($loop['else'] === true)
            return '<?php endif; ?>';

        return '<?php endforeach; endif; ?>';

    }

    protected function compileLDELIM($tag){

        return $this->ldelim;

    }

    protected function compileRDELIM($tag){

        return $this->rdelim;

    }

    protected function compileCAPTURE($params){

        $params = $this->parsePARAMS($params);

        if(!array_key_exists('name', $params))
            return '';

        $this->__capture_stack[] = $params;

        return '<?php ob_start(); ?>';

    }

    protected function compileENDCAPTURE(){

        $params = array_pop($this->__capture_stack);

        $code = '<?php $' . $this->compileVAR('smarty.capture.' . $params['name']);

        if(array_key_exists('assign', $params))
            $code .= ' = $' . $this->compileVAR($params['assign']);

        return $code . ' = ob_get_clean(); ?>';

    }

    protected function compileASSIGN($params){

        $params = $this->parsePARAMS($params);

        if(!(array_key_exists('var', $params) && array_key_exists('value', $params)))
            return null;

        return "<?php $" . trim($params['var'], "'") . "={$params['value']};?>";

    }

    protected function compileFUNCTION($params){

        $params = $this->parsePARAMS($params);

        if(!($name = ake($params, 'name')) || array_key_exists($name, $this->__custom_functions))
            return null;

        unset($params['name']);

        $this->__custom_functions[$name] = $params;

        $code = "<?php (\$this->functions['{$name}'] = function(\$params){ global \$smarty; extract(\$params); ?>";

        return $code;

    }

    protected function compileENDFUNCTION(){

        return '<?php })->bindTo($this); ?>';

    }

    protected function compileCUSTOMFUNC($name, $params){

        if(!array_key_exists($name, $this->__custom_functions))
            return null;

        $code = "<?php \$this->functions['{$name}'](";

        $params = array_merge($this->__custom_functions[$name], $this->parsePARAMS($params));

        if(count($params) > 0){

            $parts = array();

            foreach($params as $key => $value)
                $parts[] = "'$key' => " . $this->compileVAR($value);

            $code .= '[' . implode(', ', $parts) . ']';

        }

        $code .= "); ?>";

        return $code;

    }

    protected function compileCUSTOMHANDLERFUNC($method, $params){

        $params = $this->parsePARAMS($params);

        $reflect = new \ReflectionMethod($this->__custom_function_handler, $method);

        $func_params = array();

        foreach($reflect->getParameters() as $p){

            $name = $p->getName();

            $value = 'null';

            if(array_key_exists($name, $params)){

                $value = $this->compilePARAMS($params[$name]);

            }elseif($p->isDefaultValueAvailable()){

                $value = ake($params, $p->getName(), ($p->isDefaultValueAvailable() ? $p->getDefaultValue() : null));

                $value = $this->compilePARAMS($value);

            }

            $func_params[$p->getPosition()] = $value;

        }

        $params = implode(', ', $func_params);

        return "<?php echo call_user_func_array(array(\$this->custom_handler, '$method'), array($params)); ?>";

    }

}
