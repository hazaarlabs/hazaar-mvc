<?php
/**
 * Created by PhpStorm.
 * User: jamie
 * Date: 2/02/16
 * Time: 4:15 PM
 */

namespace Hazaar\File\Renderer;

class GD extends BaseRenderer {

    private $img;

    private $quality = 100;

    public function load($bytes){

        if($this->img = imagecreatefromstring($bytes))
            $this->loaded = true;

    }

    public function read(){

        return imagejpeg($this->img);

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

        ob_start();

        switch($this->mime_content_type()) {
            case 'image/jpg' :
            case 'image/jpeg' :

                imagejpeg($this->img, NULL, $quality);

                return TRUE;

            case 'image/png' :
                imagesavealpha($this->img, TRUE);

                imagepng($this->img, NULL, ($quality / 10) - 1);

                return TRUE;
        }

        $this->set_contents(ob_end_clean());

        return FALSE;

    }

    public function resize($width = NULL, $height = NULL, $crop = FALSE, $align = NULL, $keep_aspect = TRUE, $reduce_only = TRUE, $ratio = NULL, $offsetTop = 0, $offsetLeft = 0) {

        /*
         * Initialize the source dimenstions
         */

        $src_w = imagesx($this->img);

        $src_h = imagesy($this->img);

        if(! $ratio)
            $ratio = $src_h / $src_w;

        if($reduce_only) {

            $width = min($src_w, intval($width));

            $height = min($src_h, intval($height));

            if($src_w <= $width && $src_h <= $height)
                return;

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

        $dst = imagecreate($width, $height);

        /*
         * Initialize default values for stretching
         */

        $dst_x = 0;

        $dst_y = 0;

        $src_x = 0;

        $src_y = 0;

        $dst_w = $width;

        $dst_h = $height;

        /*
         * Otherwise, check if we are cropping and figure out what area we want
         */
        if($crop == TRUE) {

            $ratio2 = $dst_h / $dst_w;

            if($ratio2 < $ratio) {

                $dst_y = ceil(($dst_h - ($dst_w * $ratio)) / 2);

                $dst_h -= ($dst_y * 2);

            } else {

                $dst_x = ceil(($dst_w - ($dst_h / $ratio)) / 2);

                $dst_w -= ($dst_x * 2);

            }

        }

        /*
         * Do the actual resize
         */
        imagecopyresampled($dst, $this->img, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);

        /*
         * Update the content with the resized image data
         */
        ob_start();

        switch($this->mime_content_type()) {
            case 'image/gif' :
                imagegif($dst);

                break;

            case 'image/jpg' :
            case 'image/jpeg' :

                if($this->quality) {

                    imagejpeg($dst, NULL, $this->quality);

                    $this->quality = NULL;

                } else {

                    imagejpeg($dst);

                }

                break;

            case 'image/png' :
            default :
                imagesavealpha($dst, TRUE);

                if($this->quality) {

                    imagepng($dst, NULL, ($this->quality / 10) - 1);

                    $this->quality = NULL;

                } else {

                    imagepng($dst);

                }

                //Hack to ensure the content type is correct. In case we are converting the image from some other format, like say PDF.
                $this->set_mime_content_type('image/png');

                break;
        }

        $this->set_contents(ob_get_clean());

    }

    public function expand($xwidth = NULL, $xheight = NULL, $align = 'topleft', $offsetTop = 0, $offsetLeft = 0){

    }

}