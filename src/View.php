<?php

/**
 * @file        Hazaar/View/View.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar;

class View {

    private $name;

    private $_viewfile;

    protected $_data = array();

    /**
     * View Helpers
     */
    protected $_helpers = array();

    /**
     * Array for storing names of initialised helpers so we only initialise them once
     */
    private $_helpers_init = array();

    private $_rendering = FALSE;

    protected $_methodHandler;

    protected $_links = array();

    protected $_requires = array();

    protected $_priority = 0;

    protected $_requires_param = array();

    protected $_scripts = array();

    protected $_postItems = array();

    public function __construct($view, $init_default_helpers = TRUE) {

        $this->_helpers['application'] = Application::getInstance();

        $this->name = $view;

        $this->load($view);

        if ($init_default_helpers) {

            $this->addHelper('hazaar');

            $this->addHelper('html');

            if ($this->application->config->has('view')) {

                if ($this->application->config->view->has('require')) {

                    foreach ($this->application->config->view->require as $req) {

                        $this->requires($req);
                    }
                }

                if ($this->application->config->view->has('helper')) {

                    $load = $this->application->config->view->helper->load;

                    if (! \Hazaar\Map::is_array($load))
                        $load = array(
                            $load
                        );

                    foreach ($load as $helper) {

                        $args = array();

                        if (preg_match('/(\w*)\[(.*)\]/', $helper, $matches)) {

                            $helper = $matches[1];

                            foreach (explode(',', $matches[2]) as $arg) {

                                $kv = explode(':', $arg);

                                if (isset($kv[1])) {

                                    $val = trim($kv[1]);

                                    if (in_array(strtolower($val), array(
                                        'yes',
                                        'no',
                                        'true',
                                        'false',
                                        'on',
                                        'off'
                                    ))) {

                                        $val = boolify($val);
                                    } elseif (is_numeric($val)) {

                                        if (strpos($val, '.') === FALSE) {

                                            settype($val, 'int');
                                        } else {

                                            settype($val, 'float');
                                        }
                                    }

                                    $args[trim($kv[0])] = $val;
                                }
                            }
                        }

                        $this->addHelper($helper, $args);
                    }
                }
            }
        }

    }

    public function load($view) {

        if(Loader::isAbsolutePath($view)){

            $this->_viewfile = $view;

        }else{

            $type = FILE_PATH_VIEW;

            /*
             * If the name begins with an @ symbol then we are trying to load the view from an include path, not the application path
             */
            if (substr($view, 0, 1) == '@') {

                $view = substr($view, 1);

                $type = FILE_PATH_SUPPORT;
            }

            $viewFile = $view . '.phtml';

            if (! ($this->_viewfile = Loader::getFilePath($type, $viewFile)))
                throw new \Exception('File not found or permission denied accessing ' . $viewFile);

        }

    }

    public function getName() {

        return $this->name;

    }

    public function getViewFile() {

        return $this->_viewfile;

    }

    public function __get($helper) {

        return $this->get($helper);

    }

    public function get($helper, $default = NULL) {

        if (array_key_exists($helper, $this->_helpers)) {

            return $this->_helpers[$helper];
        } elseif (array_key_exists($helper, $this->_data)) {

            return $this->_data[$helper];
        }

        return $default;

    }

    public function __set($key, $value) {

        $this->set($key, $value);

    }

    public function set($key, $value) {

        $this->_data[$key] = $value;

    }

    public function __isset($key) {

        return $this->has($key);

    }

    public function has($key) {

        return array_key_exists($key, $this->_data);

    }

    public function __unset($key) {

        $this->unset($key);

    }

    public function remove($key) {

        unset($this->_data[$key]);

    }

    public function populate(array $array) {

        $this->_data = $array;

    }

    public function extend(array $array) {

        $this->_data = array_merge($this->_data, $array);

    }

    public function getData() {

        return $this->_data;

    }

    /**
     * Registers a controller on the view for method callbacks.
     *
     * @param
     *            $controller
     *
     * @deprecated Replaced by Hazaar\View::registerMethodHandler().
     */
    public function registerController($controller) {

        $this->registerMethodHandler($controller);

    }

    /**
     * Registers an object on the view for method callbacks.
     *
     * @throws \Exception
     *
     * @param
     *            $handler
     */
    public function registerMethodHandler($handler) {

        if (! is_object($handler))
            throw new \Exception('Error trying to register handler that is not an object.');

        $this->_methodHandler = $handler;

    }

    /*
     * Method router. Calls any unknown methods back on the controller
     */
    public function __call($method, $args) {

        if (! $this->_methodHandler)
            throw new \Exception('No method handler defined!');

        if (method_exists($this->_methodHandler, $method)) {

            return call_user_func_array(array(
                $this->_methodHandler,
                $method
            ), $args);
        } elseif (array_key_exists($method, $this->_helpers) && method_exists($this->_helpers[$method], '__default')) {

            return call_user_func_array(array(
                $this->_helpers[$method],
                '__default'
            ), $args);
        }

        throw new \Exception("Method not found calling " . get_class($this->_methodHandler) . ":$method()");

    }

    /*
     * Dealing with Helpers
     */
    public function addHelper($helper, $args = array()) {

        if (is_array($helper)) {

            foreach ($helper as $h)
                self::addHelper($h);
        } else
            if (is_object($helper)) {

                if (! $helper instanceof View\Helper)
                    return NULL;

                $id = strtolower($helper->getName());

                $this->_helpers[$id] = $helper;
            } else {

                $id = strtolower($helper);

                if (! array_key_exists($id, $this->_helpers)) {

                    $class = '\\Hazaar\\View\\Helper\\' . ucfirst($helper);

                    $obj = new $class($this, $args);

                    $this->_helpers[$id] = $obj;
                } else {

                    if (($obj = $this->_helpers[$id]) instanceof View\Helper)
                        $obj->extend($args);
                }
            }

    }

    public function hasHelper($helper) {

        return array_key_exists($helper, $this->_helpers);

    }

    public function getHelpers() {

        return array_keys($this->_helpers);

    }

    public function &getHelper($key) {

        if (array_key_exists($key, $this->_helpers))
            return $this->_helpers[$key];

        return NULL;

    }

    public function setImportPriority($priority) {

        $this->_priority = $priority;

    }

    public function import() {

        $out = '';

        $local = (string) $this->application->url();

        if (count($this->_links) > 0) {

            if ($this->_requires_param) {

                foreach ($this->_links as $priority => & $req) {

                    foreach ($req as $r) {

                        $uri = new \Hazaar\Http\Uri($r->parameters()->get('href'));

                        if (substr((string) $uri, 0, strlen($local)) != $local)
                            continue;

                        $uri->setParams($this->_requires_param);

                        $r->parameters()->set('href', $uri);

                    }

                }

            }

            krsort($this->_links);

            foreach ($this->_links as $req)
                $out .= implode("\n", $req) . "\n";

            $out .= "\n";

        }

        return $out;

    }

    public function post() {

        $out = '';

        if (count($this->_requires) > 0) {

            if ($this->_requires_param) {

                foreach ($this->_requires as $priority => & $req) {

                    foreach ($req as $r) {

                        $uri = new \Hazaar\Http\Uri($r->parameters()->get('src'));

                        if (substr((string) $uri, 0, strlen($local)) != $local)
                            continue;

                        $uri->setParams($this->_requires_param);

                        $r->parameters()->set('src', $uri);
                    }
                }
            }

            krsort($this->_requires);

            foreach ($this->_requires as $priority => & $req)
                $out .= implode("\n", $req) . "\n";

            $out .= "\n";

        }

        foreach ($this->_postItems as $item) {

            if ($item instanceof View)
                $out .= $item->render();

            elseif ($item instanceof \Hazaar\Html\Script)
                $out .= $item->renderObject();

        }

        if (count($this->_scripts) > 0)
            $out .= implode("\n", $this->_scripts) . "\n";

        foreach ($this->_helpers as $helper) {

            if (method_exists($helper, 'post'))
                $out .= $helper->post();

        }

        return $out;

    }

    public function addPost($item) {

        $this->_postItems[] = $item;

    }

    public function initHelpers() {

        $this->_priority = 1;

        foreach ($this->_helpers as $helper) {

            if ($helper instanceof \Hazaar\View\Helper) {

                $name = get_class($helper);

                if (! in_array($name, $this->_helpers_init)) {

                    $helper->initialise();

                    $this->_helpers_init[] = $name;
                }
            }
        }

        $this->_priority = 0;

        return TRUE;

    }

    /*
     * Rendering the view
     */
    public function render() {

        if ($this->_rendering)
            return NULL;

        $this->_rendering = TRUE;

        $output = '';

        ob_start();

        if (! ($file = $this->getViewFile()) || ! file_exists($file)) {

            throw new \Exception("View does not exist ($this->name)", 404);
        }

        include ($file);

        $output = ob_get_contents();

        ob_end_clean();

        $this->_rendering = FALSE;

        return $output;

    }

    /*
     * Render a partial view
     */
    public function partial($view, array $data = array()) {

        /*
         * This converts "absolute paths" to paths that are relative to FILE_PATH_VIEW.
         *
         * Relative paths are then made relative to the current view (using it's absolute path).
         */
        $fChar = substr($view, 0, 1);

        if ($fChar == '/')
            $view = substr($view, 1);
        else
            $view = dirname($this->_viewfile) . '/' . $view . '.phtml';

        $output = '';

        if ($partial = new View($view)) {

            $partial->registerMethodHandler($this->_methodHandler);

            $partial->addHelper($this->_helpers);

            $partial->populate($data);

            $output = $partial->render();
        }

        return $output;

    }

    public function setRequiresParam($array) {

        $this->_requires_param = array_merge($this->_requires_param, $array);

    }

    public function requires($script, $charset = NULL) {

        if (is_array($script)) {

            foreach ($script as $s)
                $this->requires($s, $charset);

            return;
        }

        if (! $script instanceof \Hazaar\Html\Script) {

            if (! preg_match('/^http[s]?:\/\//', $script)) {

                $script = $this->application->url('script/' . $script);
            }

            $script = $this->html->script()->src($script);

            if ($charset)
                $script->charset($charset);
        }

        $this->_requires[$this->_priority][] = $script;

    }

    public function link($href, $rel = NULL) {

        if (! $rel) {

            $info = pathinfo($href);

            if ((array_key_exists('extension', $info) && $info['extension'] == 'css') || ! array_key_exists('extension', $info)) {

                $rel = 'stylesheet';
            } elseif ($info['filename'] == 'favicon') {

                $rel = 'shortcut icon';
            }
        }

        if (! preg_match('/^http[s]?:\/\//', $href)) {

            switch ($rel) {
                case 'stylesheet':
                    $href = $this->application->url('style/' . $href);

                    break;

                case 'shortcut icon':
                    $href = $this->application->url($href);

                    break;
            }
        }

        $link = $this->html->inline('link')
            ->rel($rel)
            ->href($href);

        $this->_links[$this->_priority][] = $link;

        return $link;

    }

    public function script($code) {

        $this->_scripts[] = $this->html->script($code);

    }

    /*
     * Render a partial view multiple times on an array
     */
    public function partialLoop($view, array $data) {

        $output = '';

        foreach ($data as $d) {

            $output .= $this->partial($view, $d);
        }

        return $output;

    }

    /*
     * Built-in helper methods
     */

    /*
     * Returns a date string formatted to the current set date format
     */
    public function date($date) {

        if (! ($date instanceof \Hazaar\Date)) {

            $date = new Date($date);
        }

        return $date->date();

    }

    static public function timestamp($value) {

        if (! ($value instanceof \Hazaar\Date)) {

            $value = new Date($value);
        }

        return $value->timestamp();

    }

    static public function datetime($value, $format = NULL) {

        if (! ($value instanceof \Hazaar\Date)) {

            $value = new Date($value);
        }

        if ($format)
            return $value->format($format);

        return $value->datetime();

    }

    public function yn($value, $labels = array(
        'Yes',
        'No'
    )) {

        return (boolify($value) ? $labels[0] : $labels[1]);

    }

}
