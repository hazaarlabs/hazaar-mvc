<?php

namespace Hazaar\View;

/**
 * The View\Template class
 *
 * Templates are used to separate view content from application logic.  These templates use a simple
 * tag substitution technique to apply data to templates to generate content.  Data can be applied
 * to templates multiple times (in a loop for example) to generate multiple output content containing
 * different values.  This is useful for tasks such as mail-merges/mass mailouts using a pre-defined
 * email template.
 *
 * Tags are in the format of ${tagname}.  This tag would reference a parameter passed to the parser
 * with the array key value of 'tagname'.  Such as:
 *
 * <code>
 * $tpl->parse(array('tagname' => 'Hello, World!'));
 * </code>
 *
 */
class Template {

    protected $content   = null;

    public    $nullvalue = 'NULL';

    static private $tags = array('if', 'else', 'elseif', 'endif');

    function __construct($content = null){

        if($content)
            $this->content = $content;

    }

    public function loadFromString($content) {

        $this->content = (string)$content;

    }

    public function loadFromFile($filename) {

        $this->content = file_get_contents($filename);

    }

    public function parse($params = array(), $use_defaults = true) {

        if($use_defaults){

            $default_params = array(
                '_COOKIE' => $_COOKIE,
                '_ENV' => $_ENV,
                '_GET' => $_GET,
                '_POST' => $_POST,
                '_REQUEST' => $_REQUEST,
                '_SERVER' => $_SERVER,
                'config' => \Hazaar\Application::getInstance()->config->toArray(),
                'now' => new \Hazaar\Date()
            );

            $params = array_merge($default_params, $params);

        }

        $id = '_template_' . md5(uniqid());

        $compiled = $this->compile($id, $this->content);

        dump($compiled);

        eval($compiled);

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

    private function compile($id, $text){

        $compiled_text = $text;

        $funcs = array('if', 'elseif', 'else', 'foreach', 'section');

        while(preg_match_all('/\{(\$[\w\.\[\]]+|\/?\w+)\s*(.*)\}/', $compiled_text, $matches)){

            foreach($matches[0] as $idx => $match){

                $replacement = '';

                //It matched a variable
                if(substr($matches[1][$idx], 0, 1) === '$'){

                    $replacement = $this->replaceVAR($matches[1][$idx]);

                }elseif((substr($matches[1][$idx], 0, 1) == '/' && in_array(substr($matches[1][$idx], 1), $funcs))
                    || in_array($matches[1][$idx], $funcs)){

                    $func = 'compile' . str_replace('/', 'END', strtoupper($matches[1][$idx]));

                    $replacement = $this->$func($matches[2][$idx]);

                }else{

                    $replacement = $this->compileSECTIONVAR($matches[1][$idx]);

                }

                $compiled_text = str_replace($match, $replacement, $compiled_text);

            }

        }

        dump($compiled_text);

        return "class $id {\n\tpublic function render(\$params){\n\textract(\$params);?>\n$compiled_text\n\t<?php }\n}";

    }

    private function compileVAR($name){

        $parts = preg_split('/(\.|->|\[)/', $name);

        $name = array_shift($parts);

        if(count($parts) > 0){

            foreach($parts as $part){

                if(!$part) continue;

                if(substr($part, 0, 1) == '$')
                    $name .= "[$part]";
                elseif(substr($part, -1) == ']')
                    $name .= '[$__section_' . substr($part, 0, -1) . ']';
                else
                    $name .= "['$part']";

            }

        }

        return $name;

    }

    private function replaceVAR($name){

        return '<?php echo ' . $this->compileVAR($name) . ';?>';

    }

    private function compilePARAMS($params){

        if(preg_match_all('/\$\w[\w\.\$]+/', $params, $matches)){

            foreach($matches[0] as $match)
                $params = str_replace($match, $this->compileVAR($match), $params);

        }

        return $params;

    }

    private function compileIF($params){

        return '<?php if(' . $this->compilePARAMS($params) . '): ?>';

    }

    private function compileELSEIF($params){

        return '<?php elseif(' . $this->compilePARAMS($params) . '): ?>';

    }

    private function compileELSE($params){

        return '<?php else: ?>';

    }

    private function compileENDIF($tag){

        return '<?php endif; ?>';

    }

    private function compileSECTIONVAR($name){

        dump($name);

    }

    private function compileSECTION($params){

        $parts = preg_split('/\s+/', $params);

        $params = array();

        foreach($parts as $part)
            $params += array_unflatten($part);

        //Make sure we have the name and loop required parameters.
        if(!(($name = ake($params, 'name')) && $loop = ake($params, 'loop')))
            return '';

        $var = '$__section_' . $name;

        $count = '$__count_' . $name;

        $code = '<?php for(' . $count . '=1, ' . $var . '=' . ake($params, 'start', 0) . '; ';

        $code .= $var . '<' . (is_numeric($loop) ? $loop : 'count(' . $loop . ')') . '; ';

        $code .= $count . '++, ' . $var . '+=' . ake($params, 'step', 1) . '): ';

        if($max = ake($params, 'max'))
            $code .= 'if(' . $count . '>' . $max . ') break; ';

        $code .= '?>';

        return $code;

    }

    private function compileENDSECTION($tag){

        return '<?php endfor; ?>';

    }

}
