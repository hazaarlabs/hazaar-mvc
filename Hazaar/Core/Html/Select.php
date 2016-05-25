<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML select class.
 *
 * @detail      Displays an HTML &lt;select&gt; element.
 *
 * @since       1.1
 */
class Select extends Block {

    private $value;

    /**
     * @detail      The HTML select constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements
     *                                or arrays.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     */
    function __construct($name = NULL, $options = NULL, $value = NULL, $params = array()) {

        $params['name'] = $name;

        parent::__construct('select', NULL, $params);

        $this->value = $value;

        if($options)
            $this->add($options);

    }

    public function add() {

        foreach(func_get_args() as $arg) {

            if(is_array($arg)) {

                foreach($arg as $value => $label)
                    $this->addOption($label, $value);

                return $this;

            } else if(! $arg instanceof Option && ! $arg instanceof Optgroup) {

                throw new \Exception('Only elements of type Option or Optgroup are allowed to be added to a Select object.');

            } else {

                parent::add($arg);

            }

        }

        return $this;

    }

    public function addOptgroup($label, $options = array()) {

        return self::add(array('label' => $label, 'items' => $options));

    }

    public function addOption($label, $value = NULL, $params = array()) {

        if(is_array($label) && array_key_exists('items', $label)) {

            $item = new Optgroup($label['label'], $label['items']);

        } elseif($label instanceof Optgroup) {

            $item = $label;

        } else {

            $item = new Option($label, $value, $params);

        }

        return $this->add($item);

    }

    public function renderElement($child) {

        if($child instanceof Option) {

            if($child->value == $this->value)
                $child->selected = TRUE;

        } elseif($child instanceof Optgroup) {

            foreach($child->get() as $option) {

                if($option->value == $this->value)
                    $option->selected = TRUE;

            }

        }

        return parent::renderElement($child);

    }

}


