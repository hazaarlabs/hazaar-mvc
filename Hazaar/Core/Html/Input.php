<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML input class.
 *
 * @detail      Displays an HTML &lt;input&gt; element.
 *
 * @since       1.1
 */
class Input extends Inline {

    private $datalist;

    /**
     * @detail      The HTML form constructor.
     *
     * @since       1.1
     *
     * @param       mixed  $type       The input type can be button, checkbox, color, date, datetime, datetime-local,
     *                                 email, file, hidden, image, month, number, password, radio, range, reset,
     *                                 search, submit, tel, text, time, url or week.
     *
     * @param       string $name       The name of the input field.
     *
     * @param       mixed  $value      Specifies the value of the input element.
     *
     * @param       array  $parameters Optional parameters to apply to the anchor.
     */
    function __construct($type, $name, $value = NULL, $parameters = array()) {

        $parameters['type'] = $type;

        $parameters['name'] = $name;

        if(strtolower($type) == 'checkbox') {

            if(boolify($value))
                $parameters[] = 'checked';

        } else {

            if($value)
                $parameters['value'] = $value;

        }

        parent::__construct('input', $parameters);

    }

    public function datalist($datalist) {

        if(is_array($datalist)) {

            $datalist = new Datalist($datalist);

        }

        if($datalist instanceof Datalist) {

            $this->datalist = $datalist;

            $datalist = $datalist->getParam('id');

        }

        return $this->setParam('list', $datalist);

    }

    public function renderObject() {

        $list = NULL;

        if($this->datalist instanceof Datalist) {

            $list = $this->datalist;

        }

        return parent::renderObject() . $list;

    }

}

