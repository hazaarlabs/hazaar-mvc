<?php
/**
 * @file        Hazaar/File/Renderer/GD.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2018 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\File\Renderer;

class GD extends BaseRenderer {

    private $img;

    private $type;

    private $quality = 100;

    public function load($bytes, $type = 'jpeg'){

        if($this->img = imagecreatefromstring($bytes))
            $this->loaded = true;

        $this->type = $type;

    }

    public function read(){

        ob_start();

        switch($this->type) {
            case 'gif' :

                imagegif($this->img);

                break;

            case 'png' :

                imagesavealpha($this->img, TRUE);

                if($this->quality) {

                    imagepng($this->img, NULL, ($this->quality / 10) - 1);

                    $this->quality = NULL;

                } else {

                    imagepng($this->img);

                }

                break;


            case 'jpeg':
            default :

                if(is_numeric($this->quality)) {

                    imagejpeg($this->img, NULL, intval($this->quality));

                    $this->quality = NULL;

                } else {

                    imagejpeg($this->img);

                }

                break;

        }

        return ob_get_clean();

    }

    public function quality($quality = NULL){

        $this->quality = $quality;

    }

    public function width(){

        return imagesx($this->img);

    }

    public function height(){

        return imagesy($this->img);

    }

    public function compress($quality) {

        return $this->quality($quality);

    }

    public function resize($width = NULL, $height = NULL, $crop = FALSE, $align = NULL, $keep_aspect = TRUE, $reduce_only = TRUE, $ratio = NULL, $offsetTop = 0, $offsetLeft = 0) {

        /*
         * Initialize the source dimenstions
         */

        $src_x = 0;

        $src_y = 0;

        $src_w = imagesx($this->img);

        $src_h = imagesy($this->img);

        if(! $ratio)
            $ratio = $src_h / $src_w;

        if($reduce_only) {

            $width = min($src_w, intval($width));

            $height = min($src_h, intval($height));

            if($src_w <= $width && $src_h <= $height)
                return null;

        }

        /*
         * Stretching is the default behavior, but if we haven't been given a height
         * we are going to maintain the aspect ratio when resizing.  This overrides
         * the $crop = true because we can't crop if we have no height constraint
         *
         * If we aren't given a width OR a height, make a 100x100 thumbnail
         */
        if(! $width > 0 && ! $height > 0) {

            $width = 100;

            $height = 100;

        } elseif(! $width > 0) {

            $width = $keep_aspect ? floor($height / $ratio) : $src_w;

            $crop = FALSE;

        } elseif(! $height > 0) {

            if($ratio > 1) {

                $height = $width;

                $width = floor($height / $ratio);

            } else {

                $height = floor($width * $ratio);

            }

            $crop = FALSE;

        }

        $dst = imagecreatetruecolor($width, $height);

        /*
         * Check if we are cropping and figure out what area we want
         */
        if($crop == TRUE) {

            //The target height scaled to the original image
            $scale_height = ($src_w / $width) * $height;

            //The target height scaled to the original image
            $scale_width = ($src_h / $height) * $width;

            if($scale_height < $src_h){

                if($align == 'top')
                    $src_y = 0;
                elseif($align == 'bottom')
                    $src_y = ceil($src_h - $scale_height);
                else
                    $src_y = ceil(($src_h / 2) - ($scale_height / 2));

                $src_h = ceil($scale_height);

            }

            if($scale_width < $src_w){

                if($align == 'left')
                    $src_x = 0;
                elseif($align == 'right')
                    $src_x = ceil($src_w - $scale_width);
                else
                    $src_x = ceil(($src_w / 2) - ($scale_width / 2));

                $src_w = ceil($scale_width);

            }

        }

        /*
         * Do the actual resize
         */
        if(!imagecopyresampled($dst, $this->img, 0, 0, $src_x, $src_y, $width, $height, $src_w, $src_h))
            return false;

        $this->img = $dst;

        return true;

    }

    public function expand($xwidth = NULL, $xheight = NULL, $align = 'topleft', $offsetTop = 0, $offsetLeft = 0){

    }

}