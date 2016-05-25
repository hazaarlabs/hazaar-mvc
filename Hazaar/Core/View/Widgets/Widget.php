<?php

/**
 * @brief       jqWidgets Wrappers
 *
 * @detail      A collection of wrapper objects for the jqWidgets widget framework.
 *
 * @since       1.1
 */
namespace Hazaar\View\Widgets;

/**
 * @detail          Base widget class
 *
 * @since           1.0.0
 */
abstract class Widget extends \Hazaar\View\ViewableObject {

    protected $name;

    protected $element;

    protected $script;

    private $properties;

    private $events;

    private $rendered = false;

    private $jquery;

    /**
     * @detail      Widget base constructor.
     *
     * @param       string $type The type of HTML element to use, such as DIV or INPUT.
     *
     * @param       array $params Extra params to set on the HTML element.
     *
     * @param       bool $inline Whether or not the element should be inline or block.
     *
     * @param       mixed $content Any content that should go inside an inline block.
     */
    function __construct($type, $name, $params = array(), $inline = false, $content = null) {

        if(substr($name, 0, 1) == '#') {

            $this->name = substr($name, 1);

        } else {

            $this->name = $name;

            $params['id'] = $name;

            if($inline) {

                $this->element = new \Hazaar\Html\Inline($type, $params);

            } else {

                $this->element = new \Hazaar\Html\Block($type, $content, $params);

            }

        }

        $this->reset();

        $this->jquery = \Hazaar\Html\jQuery::getInstance();

    }

    public function style($value) {

        $this->element->style($value);

        return $this;

    }

    public function add() {

        $this->element->add(func_get_args());

        return $this;

    }

    private function reset() {

        $this->properties = new JSONObject();

        $this->events = new \Hazaar\Map();

    }

    /**
     * @detail      Returns the name of the object class.  This is used to generate the jqxWidget plugin method name.
     *
     * @return      string
     */
    protected function name() {

        $fqcn = get_class($this);

        return substr($fqcn, strrpos($fqcn, '\\') + 1);

    }

    /**
     * @detail      Set an attribute on the HTML element part of the Widget.  This differs from Widget::set() as
     *              that method sets parameters that are sent to the jqWidgets library.  This method sets parameters
     *              on the HTML element that will be turned into the Widget (usually a DIV or INPUT).
     *
     * @return      \Hazaar\View\Widgets\Widget A reference to the current object.
     */
    public function attr($key, $value = null) {

        if($value === null)
            return $this->element->get($key);

        $this->element->attr($key, $value);

        return $this;

    }

    /**
     * @detail      Method to render the widget into HTML.
     *
     * @return      string The widget rendererd as HTML.
     */
    public function renderObject() {

        $script = '';

        if(!$this->rendered) {

            $script = '$(\'#' . $this->jquery->escapeSelector($this->name);

            if($this->properties->count() > 0) {

                $properties = '$.extend( { theme : jqxTheme }, ' . $this->properties->renderObject() . ' )';

            } else {

                $properties = '{ theme : jqxTheme }';
            }

            $script .= '\').jqx' . $this->name() . "( $properties )";

            if($this->events->count() > 0) {

                foreach($this->events as $event) {

                    $script .= '.on("' . $event->name() . '", ' . $event->script() . ' )';

                }

            }

            $this->rendered = true;

            $this->reset();

            $this->jquery->exec($script . ';');

            return $this->element;

        }

        $script = array();

        if($this->properties->count() > 0) {

            $script[] = '$("#' . $this->jquery->escapeSelector($this->name);

            $script[] = '").jqx' . $this->name() . '( ' . $this->properties->renderObject() . ' )';

        }

        if($this->events->count() > 0) {

            $script[] = '$("#' . $this->jquery->escapeSelector($this->name) . '")';

            foreach($this->events as $event) {

                $script[] = '.on("' . $event->name() . '", ' . $event->script() . ' )';

            }

        }

        return implode($script);

    }

    /**
     * @detail      Sets a parameter that is sent to the DOM object.
     *
     *              This method can be used to set a single parameter, with the $key and $value arguments, or
     *              multiple parameters by just using the $key argument which is an array of key/value pairs
     *              of parameters that are to be set.
     *
     * @param       mixed $key The name of the parameter to be set, or an array of key/value pairs listing
     *              multiple parameters to be set.
     *
     * @param       mixed $value (Optional) The value of the parameter if only specifying one parameter.
     *
     * @param       string $type (Optional) The data type of the value.  If set then this will be set explicitly.
     *
     * @return      \Hazaar\View\Widgets\Widget A reference to the current object.
     */
    public function set($key, $value = null, $type = null) {

        if(is_array($key)) {

            foreach($key as $k => $v) {

                $this->set($k, $v);

            }

        } else {

            if($value instanceof JavaScript && $this->rendered) {

                $value->anon(false);

            }

            $this->properties->set($key, $value, $type);

        }

        if($this->rendered) {

            $script[] = '$(\'#' . $this->jquery->escapeSelector($this->name);

            $script[] = '\').jqx' . $this->name() . '( { ' . $key . ' : ' . $this->properties->get($key, true) . ' } )';

            return new JavaScript(implode($script));

        }

        return $this;

    }

    /**
     * @detail      Gets a widget property that has already been set
     *
     * @param       string $key The property to return
     *
     * @return      mixed
     */
    public function get($key) {

        return $this->properties->get($key);

    }

    /**
     * @detail      Add an event to the Widget
     *
     * @param       string $name The name of the event.  eg: 'click'
     *
     * @param       mixed $code The JavaScript code to execute either as a string or a JavaScript object.
     *
     * @return      \Hazaar\View\Widgets\Widget
     */
    public function event($name, $code) {

        $this->events[] = new Event($name, $code);

        return $this;

    }

    /**
     * @detail      Call a jqWidgets method using the jqWidgets interface.  Methods are called using this interface
     *              by specifying the first argument as the method being called and subsequent arguments are the actual
     *              arguments for the method call.
     *
     * @param       string $name The method to call.
     *
     * @return      \Hazaar\View\Widgets\Widget
     */
    public function method($name) {

        $script = array();

        $script[] = '$(\'#' . $this->jquery->escapeSelector($this->name);

        $script[] = '\').jqx' . $this->name() . "('$name'";

        $argarray = func_get_args();

        unset($argarray[0]);

        //Remove any trailing null arguments.
        for($i = count($argarray); $i > 0; $i--) {

            if($argarray[$i] !== null)
                break;

            unset($argarray[$i]);

        }

        if(count($argarray) > 0) {

            $p = new JSONObject($argarray);

            $script[] = ', ' . $p->renderProperties();

        }

        $script[] = ');';

        if($this->rendered) {

            return new JavaScript(implode($script));

        }

        $this->exec($script);

        return $this;

    }

    public function exec($script) {

        if(is_array($script))
            $script = implode($script);

        $this->jquery->postExec($script);

    }

    /**
     * @detail      Like method(), except this will call an actual method on the object.  Useful for calling built-in on
     *              other jQuery methods such as val().
     *
     * @param       string $name The method to call.
     *
     * @return      \Hazaar\View\Widgets\Widget
     */
    protected function call($method) {

        $script = array();

        $script[] = '$(\'#' . $this->jquery->escapeSelector($this->name);

        $script[] = '\').' . $method . '(';

        $argarray = func_get_args();

        unset($argarray[0]);

        //Remove any trailing null arguments.
        for($i = count($argarray); $i > 0; $i--) {

            if($argarray[$i] !== null)
                break;

            unset($argarray[$i]);

        }

        if(count($argarray) > 0) {

            $p = new JSONObject($argarray);

            $script[] = ', ' . $p->renderProperties();

        }

        $script[] = ')';

        return new JavaScript(implode($script));

    }

    /**
     * @detail      Specifies the theme to use when initialising the widget.  This is not normally needed
     *              as the default theme is set when creating each widget.  However you may want to override
     *              the theme on a per widget basis and this allows for that.
     *
     *              <div class="alert alert-info">Keep in mind that the theme file used will have to included
     *              manually.</div>
     *
     * @param       string $value The name of the theme.
     *
     * @return      \Hazaar\View\Widgets\Widget A reference to the current object.
     */

    public function theme($value) {

        return $this->set('theme', $value);

    }

    /**
     * @detail      Specifies the width of the widget in pixels.
     *
     * @param       mixed $value The width value.  Either an integer or string (with px suffix);
     *
     * @return      \Hazaar\View\Widgets\Widget A reference to the current object.
     */
    public function width($value) {

        return $this->set('width', $value);

    }

    /**
     * @detail      Specifies the height of the widget in pixels.
     *
     * @param       mixed $value The height value.  Either an integer or string (with px suffix);
     *
     * @return      \Hazaar\View\Widgets\Widget A reference to the current object.
     */
    public function height($value) {

        return $this->set('height', $value);

    }

    /**
     * @detail      Sets whether the widget is disabled by default or not.
     *
     * @param       bool $value True to disable, false to not.
     *
     * @return      \Hazaar\View\Widgets\Widget A reference to the current object.
     */
    public function disabled($value) {

        return $this->set('disabled', $value, 'bool');

    }

    /**
     * @detail      Adds a click event to a widget
     *
     * @param       mixed $value The code as a string or JavaScript object, or array of objects to execute.
     *
     * @return      \Hazaar\View\Widgets\Widget
     */
    public function onClick($value) {

        return $this->event('click', $value);

    }

    /**
     * @detail      Execute the focus method on a widget
     *
     * @return      string JavaScript code to execute the focus method.
     */
    public function focus() {

        return $this->method('focus');

    }

    /**
     * @detail      Execute the render method on a widget
     *
     * @return      string JavaScript code to execute the render method.
     */
    public function render() {

        return $this->method('render');

    }

    /**
     * @detail      Execute the destroy method on a widget
     *
     * @return      string JavaScript code to execute the destroy method.
     */
    public function destroy() {

        return $this->method('destroy');

    }

    /**
     * @detail      Sets or gets the value.
     *
     * @param       string $value The value to set
     *
     * @return      string
     */
    public function setContent($content) {

        return $this->method('setContent', $content);

    }

    /**
     * @detail      This method enabled the widget.
     *
     * @return      string
     */
    public function enable() {

        return $this->method('enable');

    }

    /**
     * @detail      This method disables the widget.
     *
     * @return      string
     */
    public function disable() {

        return $this->method('disable');

    }

    /**
     * @detail      Sets or gets the value.
     *
     * @param       string $value The value to set
     *
     * @return      string
     */
    public function val($value = null) {

        return $this->call('val', $value);

    }

}
