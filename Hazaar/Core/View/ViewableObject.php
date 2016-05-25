<?php

namespace Hazaar\View;

/**
 * @brief       Abstract class for objects that can be written to a string
 *
 * @detail      Classes the extend this abstract class must implement a render() method which will
 *              be called when the object is attempting to be written as a string.  These objects
 *              MUST return a string from this method that is a string representation of the object.
 *
 * @since       1.1
 */
abstract class ViewableObject implements Viewable {

    public function __tostring() {

        return (string)$this->renderObject();

    }

}