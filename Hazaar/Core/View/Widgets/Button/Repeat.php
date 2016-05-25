<?php

namespace Hazaar\View\Widgets\Button;

/**
 * @detail      Repeat button widget.
 *
 * @since       1.0.0
 */
class Repeat extends Base {

    /**
     * @detail      Set the repeat delay interval
     *
     * @param       int $value The delay in milliseconds.
     */
    public function delay($value) {

        return $this->set('delay', $value);

    }

}
