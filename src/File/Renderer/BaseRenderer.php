<?php

/**
 * BaseRenderer short summary.
 *
 * BaseRenderer description.
 *
 * @version 1.0
 * @author jamiec
 */
namespace Hazaar\File\Renderer;

interface ImageRendererInterface  {

    public function load($bytes, $type);

    public function read();

    public function loaded();

    public function quality($quality = NULL);

    public function width();

    public function height();

    public function compress($quality);

    public function resize($width = NULL, $height = NULL, $crop = FALSE, $align = NULL, $keep_aspect = TRUE, $reduce_only = TRUE, $ratio = NULL, $offsetTop = 0, $offsetLeft = 0);

    public function expand($xwidth = NULL, $xheight = NULL, $align = 'topleft', $offsetTop = 0, $offsetLeft = 0);

}

abstract class BaseRenderer implements ImageRendererInterface {

    protected $loaded = false;

    public function loaded() {

        return $this->loaded;

    }

}