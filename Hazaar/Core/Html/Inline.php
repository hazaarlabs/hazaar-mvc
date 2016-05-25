<?php

namespace Hazaar\Html;

/**
 * @brief       Inline HTML display element
 *
 * @detail      Generic base class for an HTML inline element.  This class will render any inline element of the defined
 *              type.
 * 
 * @since       1.0.0
 */
class Inline extends Element {

    /**
     * @detail      Renders the current object as an inline HTML element.
     * 
     * @since       1.0.0
     * 
     * @return      string
     */
    public function renderObject() {

        return '<' . $this->type . (($this->parameters->count() > 0) ? ' ' . $this->parameters : null) . ' />';

    }

}
