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
    function __construct($source_file, $env = NULL, $defaults = array(), $path_type = FILE_PATH_CONFIG) {

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

        if(!$this->isEmpty())
            $this->processConfig($this);

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

                if($info['extension'] == 'json')
                    $options->fromJSON(file_get_contents($this->source));

                elseif($info['extension'] == 'ini')
                    $options->fromDotNotation(parse_ini_file($this->source, TRUE, INI_SCANNER_RAW));

                if(isset($apc_key))
                    apc_store($apc_key, $options);

            }

        }



        if(! $options->has($this->env))
            return null;

        $config = new \Hazaar\Map($defaults);

        foreach($options[$this->env] as $key => $values) {

            if($key == 'include') {

                if(!\Hazaar\Map::is_array($values))
                    $values = array($values);

                foreach($values as $include_environment)
                    $config->extend($options[$include_environment]);

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

                $config->extend($values);

            }

        }

        $this->loaded = TRUE;

        return $config;

    }

    /**
     * Check whether the config was loaded from the source file.
     */
    public function loaded() {

        return $this->loaded;

    }

    /**
     * @private
     */
    private function processConfig(\Hazaar\Map $config = null) {

        if(!$config)
            $config = $this;

        foreach($config as $key => $value){

            switch($key) {

                /*
                 * Authentication parameters set static variables in the Hazaar\Auth\Adapter class.
                 */
                case 'auth':

                    foreach($value as $param) {

                        if(property_exists('\Hazaar\Auth\Adapter', $param))
                            \Hazaar\Auth\Adapter::$$param = $value;

                    }

                /*
                 * PHP root elements can be set directly with the PHP ini_set function
                 */
                case 'php' :

                    $php_values = $value->toDotNotation()->toArray();

                    foreach($php_values as $directive => $php_value)
                        ini_set($directive, $php_value);

                    break;

                case 'paths':

                    foreach($value as $param)
                        \Hazaar\Loader::getInstance()->addSearchPath($param, $value);

                    break;

            }

        }

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

    public function write() {

        if(! $this->source)
            return FALSE;

        //Grab the original file so we can merge into it
        $options = new \Hazaar\Map((file_exists($this->source) ? parse_ini_file($this->source, TRUE) : array()));

        $options->set($this->env, $this->todotnotation());

        $output = '';

        foreach($options as $env => $option) {

            $output .= "[$env]\n";

            $output .= $option->todotnotation()
                              ->flatten(' = ', "\n") . "\n";

            $output .= "\n";

        }

        $result = file_put_contents($this->source, $output);

        if($result === FALSE)
            return FALSE;

        return TRUE;

    }

}