<?php

namespace Hazaar\View;

/**
 * @brief       Interface for objects that are writable as a string.
 * 
 * @since       1.1
 */
interface Viewable {
    
    /**
     * Required method to render the object as a string.
     */
    public function renderObject();
    
    /**
     * Magic method to call the render() method.
     */
    public function __tostring();

}