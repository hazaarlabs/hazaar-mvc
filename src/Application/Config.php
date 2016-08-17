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
    function __construct($source, $env = NULL, $defaults = array(), $path_type = FILE_PATH_CONFIG) {

        $options = array();

        if(! $env)
            $env = APPLICATION_ENV;

        $this->env = $env;

        if($source = trim($source)) {

            $source = \Hazaar\Loader::getFilePath($path_type, $source, NULL, FALSE);

            $this->source = $source;

            if(file_exists($this->source)) {

                if(in_array('apc', get_loaded_extensions()))
                    $apc_key = md5(gethostname() . ':' . $this->source);

                if(isset($apc_key)) {

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
                            $options = apc_fetch($apc_key);

                    }

                }

                if(! $options && file_exists($this->source)) {

                    $options = parse_ini_file($this->source, TRUE, INI_SCANNER_RAW);

                    if(isset($apc_key))
                        apc_store($apc_key, $options);

                }

            }

        }

        if(array_key_exists($this->env, $options))
            $this->loaded = TRUE;

        $config = $this->load($options, $this->env, $defaults);

        parent::__construct($config);

    }

    public function reload() {

        $options = parse_ini_file($this->source, TRUE, INI_SCANNER_RAW);

        $config = $this->load($options, $this->env, $defaults);

        return $this->populate($config);

    }

    /**
     * @private
     */
    private function load($options, $env, &$config = NULL) {

        if(! is_array($config))
            $config = array();

        if(! array_key_exists($env, $options))
            return $config;

        foreach($options[$env] as $key => $value) {

            if($key == 'include') {

                if(! is_array($value))
                    $value = array($value);

                foreach($value as $include_environment)
                    $this->load($options, $include_environment, $config);

            } elseif($key == 'import') {

                if($file = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, $value)) {

                    if(file_exists($file)) {

                        $options = parse_ini_file($file, TRUE, INI_SCANNER_RAW);

                        $this->load($options, $env, $config);

                    }

                }

            } else {

                $this->loadConfigOption($key, $value, $config);

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

    private function parseValue($value) {

        if(is_array($value)) {

            foreach($value as &$v)
                $v = $this->parseValue($v);

        } else {

            $first = substr($value, 0, 1);

            if($first == "'" || $first == '"') {

                if(! substr($value, -1, 1) == $first)
                    throw new \Exception('Encapsulated string not closed in config file!');

                $value = trim($value, $first);

            } elseif(strtolower($value) == 'false') {

                $value = FALSE;

            } elseif(strtolower($value) == 'true') {

                $value = TRUE;

            } elseif(is_numeric($value)) {

                $value = (int)$value;

            }

        }

        return $value;

    }

    /**
     * @private
     */
    private function loadConfigOption($key, $value, &$config) {

        /*
         * Store the configuration value
         *
         * Turns the dot notation element into a multidimensional array
         */

        $value = $this->parseValue($value);

        $params = preg_split('/\./', $key);

        $array = NULL;
        //Declare the base value

        $ptr = &$array;
        //Set a 'pointer' to the first level

        foreach($params as $p) {

            $ptr[$p] = NULL;
            //Create a new child element on the current level

            $ptr = &$ptr[$p];
            //Set the 'pointer' to the new element on the next level

        }

        $ptr = $value;

        //Set the final level element to the actual value

        //Merge this array over the top of the existing values
        $config = array_replace_recursive($config, $array);

        /*
         * Act upon certain config elements
         */

        $param = array_shift($params);

        switch($param) {

            /*
             * Authentication parameters set static variables in the Hazaar\Auth\Adapter class.
             */
            case 'auth':

                foreach($params as $param) {

                    if(property_exists('\Hazaar\Auth\Adapter', $param))
                        \Hazaar\Auth\Adapter::$$param = $value;

                }

            /*
             * PHP root elements can be set directly with the PHP ini_set function
             */
            case 'php' :
                ini_set(implode('.', $params), $value);

                break;

            /*
             * If the debug option is set turn on debugging
             */
            case 'debug' :
                if($value)
                    $this->enableLogging();

                break;

            case 'paths':

                foreach($params as $param)
                    \Hazaar\Loader::getInstance()->addSearchPath($param, $value);

                break;

        }

        return $config;

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

        if(file_exists($this->source)) {

            return file_get_contents($this->source);

        }

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