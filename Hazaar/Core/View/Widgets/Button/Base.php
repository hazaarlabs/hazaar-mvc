<?php

namespace Hazaar\View\Widgets\Button;

/**
 * @detail          Toggle button widget.
 *
 * @since           1.0.0
 */
abstract class Base extends \Hazaar\View\Widgets\Button {

    public function name() {

        return parent::name() . 'Button';

    }
    
}
