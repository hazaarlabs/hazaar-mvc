<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Window widget class
 *
 * @since           1.1
 */
class Editor extends Widget {

    /**
     * @detail      Editor widget constructor
     *
     * @since       2.0.0
     *
     * @param       string $name The name of the editor widget.
     *
     * @param       string $content The initial contents of the editor
     *
     * @param       array $params Any additional parameters to set on the DIV container
     */
    function __construct($name, $content = null, $params = null) {

        parent::__construct('div', $name, $params);

        if($content)
            parent::add($content);

    }

    /**
     * @detail      Sets or gets whether the editor is disabled.
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function disabled($value) {

        return $this->set('disabled', $value, 'bool');

    }

    /**
     * @detail      Sets the tools that are visible to the user
     *
     * @since       1.1
     *
     * @param       bool $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function tools($value) {

        if(!is_array($value)) {

            $value = explode(',', $value);

        }

        return $this->set('tools', $value, 'array');

    }

    /**
     * @detail      Sets or gets window's content's html content.
     *
     * @since       1.1
     *
     * @param       string $value
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function content($value) {

        return $this->set('content', $value, 'string');

    }
    
    public function imageBrowser($value){
        
        return $this->set('imageBrowser', $value);
        
    }

    /**
     * @detail      This event is triggered when the user create new window.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @detail      This event is triggered when the window is dragging by the user.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onExecute($code) {

        return $this->event('execute', $code);

    }

    /**
     * @detail      This event is triggered when the window is dropped by the user.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onKeydown($code) {

        return $this->event('keydown', $code);

    }

    /**
     * @detail      This event is triggered when the window is displayed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onKeyup($code) {

        return $this->event('keyup', $code);

    }

    /**
     * @detail      This event is triggered when the window is closed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onSave($code) {

        return $this->event('save', $code);

    }

    /**
     * @detail      This event is triggered when the window is expanded.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onSelect($code) {

        return $this->event('select', $code);

    }

    /**
     * @detail      This event is triggered when the window is collapsed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widget\\Window
     */
    public function onModeChange($code) {

        return $this->event('modechange', $code);

    }
    
    public function save(){
        
        return $this->method('save');
        
    }

}
