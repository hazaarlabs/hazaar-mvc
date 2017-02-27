<?php
/**
 * @file        Hazaar/Application/Config.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief       The main application entry point
 */
namespace Hazaar\Application;

/**
 * @brief       Application Configuration Class
 *
 * @detail      The config class loads settings from a configuration file and configures the system ready
 *              for the application to run.  By default the config file used is application.ini and is stored
 *              in the config folder of the application path.
 *
 * @since       1.0.0
 */
class Config extends \Hazaar\Map {

    private $env;

    private $source;

    private $loaded = FALSE;

    /**
     * @detail      The application configuration constructor loads the settings from the configuration file specified
     *              in the first parameter.  It will use the second parameter as the starting point and is intended to
     *              allow different operating environments to be configured from a single configuration file.
     *
     * @since       1.0.0
     *
     * @param       string $source The absolute path to the config file
     *
     * @param       string $env The application environment to read settings for.  Usually 'development' or
     *                             'production'.
     */
    function __construct($source_file = null, $env = NULL, $defaults = array(), $path_type = FILE_PATH_CONFIG) {

        $config = null;

        $source = null;

        if(! $env)
            $env = APPLICATION_ENV;

        $this->env = $env;

        if($source_file = trim($source_file)) {

            $info = pathinfo($source_file);

            //If we have an extension, just use that file.
            if(array_key_exists('extension', $info)){

                $source = \Hazaar\Loader::getFilePath($path_type, $source_file);

            }else{ //Otherwise, search for files with supported extensions

                $extensions = array('json', 'ini'); //Ordered by preference

                foreach($extensions as $ext){

                    $filename = $source_file . '.' . $ext;

                    if($source = \Hazaar\Loader::getFilePath($path_type, $filename))
                        break;

                }

            }

            $config = $this->load($source, $defaults);

        }

        parent::__construct($config);

    }

    public function load($source = null, $defaults = array()) {

        if($source)
            $this->source = $source;
        else
            $source = $this->source;

        $options = new \Hazaar\Map();

        if(file_exists($this->source)) {

            //Check if APC is available for caching and load the config from cache.
            if(in_array('apc', get_loaded_extensions())){

                $apc_key = md5(gethostname() . ':' . $this->source);

                if(apc_exists($apc_key)) {

                    $info = apc_cache_info('user');

                    $mtime = 0;

                    foreach($info['cache_list'] as $cache) {

                        if(array_key_exists('info', $cache) && $cache['info'] == $apc_key) {

                            $mtime = ake($cache, 'mtime');

                            break;

                        }

                    }

                    if($mtime > filemtime($this->source))
                        $options->populate(apc_fetch($apc_key));

                }

            }

            if($options->isEmpty()) {

                $info = pathinfo($this->source);

                if($extension = ake($info, 'extension')){

                    if($extension == 'json')
                        $options->fromJSON(file_get_contents($this->source));

                    elseif($extension == 'ini')
                        $options->fromDotNotation(parse_ini_file($this->source, TRUE, INI_SCANNER_TYPED));

                }

                if(isset($apc_key))
                    apc_store($apc_key, $options);

            }

            $this->loaded = TRUE;

        }

        if(! $options->has($this->env))
            $options[$this->env] = array();

        $config = new \Hazaar\Map($defaults);

        if($this->loadConfigOptions($options, $config))
            return $config;

        return false;

    }

    private function loadConfigOptions(\Hazaar\Map $options, \Hazaar\Map $config, $env = null){

        if(!$env)
            $env = $this->env;

        foreach($options[$env] as $key => $values) {

            if($key == 'include') {

                if(!\Hazaar\Map::is_array($values))
                    $values = array($values);

                foreach($values as $include_environment)
                    $this->loadConfigOptions($options, $config, $include_environment);

            } elseif($key == 'import') {

                if($file = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, $values)) {

                    if(file_exists($file)) {

                        $info = pathinfo($this->source);

                        if($info['extension'] == 'json')
                            $config->fromJSON(file_get_contents($file));

                        elseif($info['extension'] == 'ini')
                            $config->fromDotNotation(parse_ini_file($file, TRUE, INI_SCANNER_RAW));

                    }

                }

            } else {

                $config->set($key, $values, true);

            }

        }

        return $config;

    }


    /**
     * Check whether the config was loaded from the source file.
     */
    public function loaded() {

        return $this->loaded;

    }

    /**
     * @brief       Get the config environment that was loaded
     *
     * @since       2.0.0
     */
    public function getEnv() {

        return $this->env;

    }

    /**
     * @brief       Get the source file content from which the settings originated
     *
     * @since       1.0.0
     */
    public function getSource() {

        if(file_exists($this->source))
            return file_get_contents($this->source);

        return 'config file not found';

    }

    public function getSourceFilename() {

        return $this->source;

    }

    /**
     * @brief       Output the configuration in a human readable format.
     *
     * @detail      This method is useful for logging, debugging or for using in application administration interfaces
     *              to check the current running configuration.
     *
     *              h3. Example Output
     *
     *              <pre>
     *              app.name = Example Application
     *              app.version = 0.0.1
     *              app.layout = application
     *              app.theme.name = test
     *              app.defaultController = Index
     *              app.compress =
     *              app.debug = 1
     *              paths.model = models
     *              paths.view = views
     *              paths.controller = controllers
     *              php.date.timezone = Australia/ACT
     *              php.display_startup_errors = 1
     *              php.display_errors = 1
     *              module.require[] = pgsql
     *              </pre>
     *
     * @since       1.0.0
     *
     * @return      string Config as a multi-line string
     */
    public function __tostring() {

        return $this->tostring();

    }

    public function tostring() {

        $config = $this->todotnotation();

        $output = "[{$this->env}]\n";

        foreach($config as $key => $value) {

            $output .= "{$key}={$value}\n";

        }

        return $output;

    }

    public function write($target = null) {

        if(!$target)
            $target = $this->source;

        if(!$target)
            return FALSE;

        $info = pathinfo($target);

        $type = ake($info, 'extension', 'json');

        $options = new \Hazaar\Map();

        //Grab the original file so we can merge into it
        if($this->source && file_exists($this->source)){

            $info = pathinfo($this->source);

            if($info['extension'] == 'json')
                $options->fromJSON(file_get_contents($this->source));

            elseif($info['extension'] == 'ini')
                $options->fromDotNotation(parse_ini_file($this->source, TRUE, INI_SCANNER_RAW));

        }

        $options->set($this->env, $this->toArray());

        $output = '';

        if($type == 'ini'){

            foreach($options as $env => $option) {

                $output .= "[$env]" . LINE_BREAK;

                $output .= $option->todotnotation()
                                  ->flatten(' = ', LINE_BREAK) . LINE_BREAK;

            }

        }else{

            $output = json_encode($this->toArray());

        }

        $result = file_put_contents($target, $output);

        if($result === FALSE)
            return FALSE;

        return TRUE;

    }

}