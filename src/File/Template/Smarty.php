<?php

namespace Hazaar\File\Template;

/**
 * The View\Template class
 *
 */
class Smarty extends \Hazaar\Template\Smarty {

    static public $cache_enabled = true;

    private $__source_file = null;

    private $__cache_enabled = false;

    function __construct($file, $cache_enabled = null){

        $this->loadFromFile($file);

        if($cache_enabled === null)
            $cache_enabled = Smarty::$cache_enabled;

        $this->__cache_enabled = $cache_enabled;

        parent::$tags[] = 'config_load';

    }

    public function loadFromFile($file) {

        if(!$file instanceof \Hazaar\File)
            $file = new \Hazaar\File($file);

        if(!$file->exists())
            throw new \Exception('Template file not found!');

        $this->__source_file = $file;

        $this->__cwd = $file->dirname();

    }

    public function compile(){

        $cache_file = null;

        if(!$this->__source_file instanceof \Hazaar\File)
            throw new \Exception('Template compilation failed! No source file or template content has been loaded!');

        if($this->__cache_enabled){

            $cache_id = md5($this->__source_file->fullpath());

            $cache_dir = new \Hazaar\File\Dir(\Hazaar\Application::getInstance()->runtimePath('template_cache', true));

            $cache_file = $cache_dir->get($cache_id . '.tpl');

            if($cache_file->exists() && $cache_file->mtime() > $this->__source_file->mtime())
                return $cache_file->get_contents();

        }

        $this->__content = $this->__source_file->get_contents();

        $this->__compiled_content = "<?php chdir('$this->__cwd'); ?>\n" . parent::compile($this->__content);

        if($cache_file)
            $cache_file->put_contents($this->__compiled_content);

        return $this->__compiled_content;

    }

    public function compileCONFIG_LOAD($params){

        $params = $this->parsePARAMS($params);

        if(!array_key_exists('file', $params))
            return '';

        $file = $this->compilePARAMS($params['file']);

        $code = '<?php ';

        if(array_key_exists('section', $params)){

            $section = $this->compilePARAMS($params['section']);

            $code .= '@$new_variables = parse_ini_file(' . $file . ', true); if($new_variables && array_key_exists(' . $section . ', $new_variables)) $this->variables = array_merge($this->variables, $new_variables[' . $section . ']);';

        }else{

            $code .= '@$this->variables = array_merge($this->variables, parse_ini_file(' . $file . '));';

        }

        $code .= '?>';

        return $code;

    }

}
