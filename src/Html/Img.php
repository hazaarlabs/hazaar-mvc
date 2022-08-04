<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML img class.
 *
 * @detail      Displays an HTML &lt;img&gt; element.
 *
 * @since       1.1
 */
class Img extends Inline {

    private $map;

    /**
     * @detail      The HTML img constructor.
     *
     * @since       1.1
     *
     * @param       mixed $src The source URL of the image.  This can be either a string or an Hazaar\Html\Url object.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($src, $alt = NULL, $parameters = []) {

        if($src) {

            $parameters['src'] = (string)$src;

            if(! $alt) {

                $alt = basename($src);

            } else {

                $parameters['title'] = $alt;

            }

            $parameters['alt'] = $alt;

        }

        parent::__construct('img', $parameters);

    }

    /**
     * @detail      Configure the image with a <map> object.  This will set the _usemap_ attribute and, if the _$map_
     *              parameter is a \Hazaar\Html\Map object, it will render the map object automatically when rendering
     *              the image.
     *
     * @since       1.2
     *
     * @param       mixed $map The name of the map to use, or a \Hazaar\Html\Map object.
     *
     * @return      \Hazaar\Html\Img
     */
    public function usemap($map) {

        if($map instanceof Map) {

            $this->map = $map;

            $map = $map->name;

        }

        return parent::usemap($map);

    }

    /**
     * @detail      Html <img> render override.  This method overrides the standard object render method and allows a
     * \Hazaar\Html\Map
     *
     * @since       1.1
     *
     * @param       mixed $src The source URL of the image.  This can be either a string or an Hazaar\Html\Map object to
     *              be prepended to the output.
     *
     * @return      string
     */
    public function renderObject() {

        $map = NULL;

        if($this->map instanceof Map) {

            $map = (string)$this->map;

        }

        return parent::renderObject() . $map;

    }

}
