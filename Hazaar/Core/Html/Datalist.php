<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML datalist class.
 *
 * @detail      Displays an HTML &lt;datalist&gt; element.
 *
 * @since       1.1
 */
class Datalist extends Block {

    /**
     * @detail      The HTML datalist constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     */
    function __construct($options = array(), $name = null, $params = array()) {

        if(!$name)
            $name = uniqid();

        $params['id'] = $name;

        parent::__construct('datalist', null, $params);

        $this->add($options);

    }

    public function add() {

        foreach(func_get_args() as $arg) {

            if(is_array($arg)) {

                foreach($arg as $value) {

                    $this->add($value);

                }

            }elseif($arg instanceof Inline){
                
                parent::add($arg);
                
            }else{
                
                parent::add(new Inline('option', array('value' => $arg)));
                
            }

        }

        return $this;

    }

}

