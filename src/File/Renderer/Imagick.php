<?php
/**
 * Created by PhpStorm.
 * User: jamie
 * Date: 2/02/16
 * Time: 4:15 PM
 */

namespace Hazaar\File\Renderer;

class Imagick extends BaseRenderer {

    private $dst;

    private $quality = NULL;

    function __construct($quality = NULL) {

        $this->dst = new \Imagick();

    }

    public function load($bytes, $type = null) {

        if(strlen($bytes) > 0)
            return $this->loaded = $this->dst->readImageBlob($bytes);

        return FALSE;

    }

    public function read() {

        return $this->dst->getImageBlob();

    }

    public function quality($quality = NULL) {

        if($quality === NULL)
            return $this->dst->getImageCompressionQuality();

        return $this->dst->setImageCompressionQuality($quality);

    }

    public function width() {

        return $this->dst->getImageGeometry()['width'];

    }

    public function height() {

        return $this->dst->getImageGeometry()['height'];

    }

    public function compress($quality) {

        $this->dst->setImageCompressionQuality($quality);

        return $this->dst->getImageBlob();

    }

    public function resize($width = NULL, $height = NULL, $crop = FALSE, $align = NULL, $keep_aspect = TRUE, $reduce_only = TRUE, $ratio = NULL, $offsetTop = 0, $offsetLeft = 0) {

        $geo = $this->dst->getImageGeometry();

        if($ratio === NULL) {

            $ratio = $geo['height'] / $geo['width'];

            if($crop)
                $keep_aspect = FALSE;

        } else {

            $ratio = floatval($ratio);

        }

        if($reduce_only) {

            $width = min($geo['width'], intval($width));

            $height = min($geo['height'], intval($height));

            if($geo['width'] <= $width && $geo['height'] <= $height)
                return;

        }

        if(! $width > 0 && ! $height > 0) { //Without a width or height, default to 100x100.

            $width = 100;

            $height = 100;

        } elseif(! $width > 0) { //Without a width, calculate it based on the aspect ratio

            $width = $keep_aspect ? intval(floor($height / $ratio)) : $geo['width'];

        } elseif(! $height > 0) { //Without a height, calculate it based on the aspect ratio

            $height = $keep_aspect ? intval(floor($width * $ratio)) : $geo['height'];

        }

        if($crop) {

            $o_ratio = $geo['height'] / $geo['width'];

            $scale_width = $width;

            $scale_height = $height;

            if($width > ($height / $o_ratio))
                $scale_height = $width * $o_ratio;

            else
                $scale_width = $height / $o_ratio;

            $this->dst->resizeImage($scale_width, $scale_height, \Imagick::FILTER_CATROM, 0.9);

            $x = 0;

            $y = 0;

            if($height < $scale_height) {

                if($align == 'bottom' || $align == 'bottomleft' || $align == 'bottomright')
                    $y = $scale_height - $height;

                elseif(! ($align == 'top' || $align == 'topleft' || $align == 'topright'))
                    $y = intval(round(($scale_height - $height) / 2));

            }

            if($width < $scale_width) {

                if($align == 'right' || $align == 'topright' || $align == 'bottomright')
                    $x = $scale_width - $width;

                elseif(! ($align == 'left' || $align == 'topleft' || $align == 'bottomleft'))
                    $x = intval(floor(($scale_width - $width) / 2));

            }

            $x += $offsetLeft;

            $y += $offsetTop;

            $this->dst->cropImage($width, $height, $x, $y);

        } else {

            $this->dst->resizeImage($width, $height, \Imagick::FILTER_CATROM, 0.9);

        }

        if($this->quality) {

            $this->dst->setImageCompressionQuality($this->quality);

            $this->quality = NULL;

        }

        return $this->dst->getImageBlob();

    }

    public function expand($xwidth = NULL, $xheight = NULL, $align = 'topleft', $offsetTop = 0, $offsetLeft = 0) {

        $geo = $this->dst->getImageGeometry();

        $width = $geo['width'];

        $height = $geo['height'];

        $x = 0;

        $y = 0;

        if($xwidth) {

            $width = $geo['width'] + intval($xwidth);

            if($align == 'right' || $align == 'topright' || $align == 'bottomright')
                $x = intval($xwidth);

            elseif(! ($align == 'left' || $align == 'topleft' || $align == 'bottomleft'))
                $x = intval($xwidth) / 2;

        }

        if($xheight) {

            $height = $geo['height'] + intval($xheight);

            if($align == 'bottom' || $align == 'bottomleft' || $align == 'bottomright')
                $y = intval($xheight);

            elseif(! ($align == 'top' || $align == 'topleft' || $align == 'topright'))
                $y = intval($xheight);

        }

        $x += $offsetLeft;

        $y += $offsetTop;

        $bg = $this->dst->getImageBackgroundColor();

        $new = new \Imagick();

        $new->newImage($width, $height, $bg);

        $new->setImageFormat($this->dst->getImageFormat());

        $new->compositeImage($this->dst, \Imagick::COMPOSITE_DEFAULT, $x, $y);

        return $new->getImageBlob();

    }

    public function importPDF($content, $dpi = 160) {

        $img = new \Imagick();

        $img->setResolution($dpi, $dpi);

        $img->readImageBlob($content);

        $this->setContent($img->getImageBlob());

        return TRUE;

    }

    public function filter($filter_def) {

        $filters = array();

        if(is_array($filter_def)) {

            $filters = $filter_def;

        } else {

            $parts = explode(';', $filter_def);

            foreach($parts as $part) {

                $values = explode(':', $part);

                $key = array_shift($values);

                $filters[$key] = $values;

            }

        }

        foreach($filters as $filter => $values) {

            switch($filter) {

                case 'blur':  //Blur

                    $value = floatval($values[0]);

                    $this->dst->blurImage($value * 2, $value);

                    break;

                case 'sharpen': //Sharpen

                    $value = floatval($values[0]);

                    $this->dst->sharpenImage($value * 2, $value);

                    break;

                case 'c': //Contrast

                    $value = intval($values[0]);

                    if($value > 0) {

                        for($i = 1; $i < $value; $i++)
                            $this->dst->contrastImage(1);

                    } else if($value <= 0) {

                        for($i = 0; $i > $value; $i--)
                            $this->dst->contrastImage(0);

                    }

                    break;

                case 'b': //Brightness

                    $value = intval($values[0]);

                    $this->dst->modulateImage($value, 100, 100);

                    break;

                case 's': //Saturation

                    $value = intval($values[0]);

                    $this->dst->modulateImage(100, $value, 100);

                    break;

                case 'h': //Hue

                    $value = intval($values[0]);

                    $this->dst->modulateImage(100, 100, $value);

                    break;

                case'mod': //Brightness, Saturation & Hue

                    list($brightness, $saturation, $hue) = $values;

                    $this->dst->modulateImage($brightness, $saturation, $hue);

                    break;

                case 'noise': //Add Noise

                    $value = floatval($values[0]);

                    for($i = 0; $i < $value; $i++)
                        $this->dst->addNoiseImage(\Imagick::NOISE_UNIFORM);

                    break;

                case 'border': //Add a border

                    $width = intval($values[0]);

                    $color = ake($values, 1, 'black');

                    $this->dst->borderImage($color, $width, $width);

                    break;

                case 'edge': //Edge enhancement

                    $value = floatval($values[0]);

                    $this->dst->edgeImage($value);

                    break;

                case 'emboss': //Emboss

                    $value = floatval($values[0]);

                    $this->dst->embossImage($value * 2, $value);

                    break;

                case 'flip':  //Vertially flip the image

                    $this->dst->flipImage();

                    break;

                case 'flop':  //Horizontally flip the image

                    $this->dst->flopImage();

                    break;

                case 'oil':  //Oil painting effect

                    $value = floatval($values[0]);

                    $this->dst->oilPaintImage($value);

                    break;

                case 'solarize':  //Solarize effect

                    $value = intval($values[0]);

                    $this->dst->solarizeImage($value);

                    break;

                case 'swirl':  //Swirl effect

                    $value = intval($values[0]);

                    $this->dst->swirlImage($value);

                    break;

                case 'transpose':  //Vertical mirror

                    $this->dst->transposeImage();

                    break;

                case 'transverse':  //Horizontal mirror

                    $this->dst->transverseImage();

                    break;

                case 'vignette':  //Add a vignette

                    $value = ake($values, 0);

                    $offset = ake($values, 1, 0);

                    $this->dst->setImageBackgroundColor('black');

                    $this->dst->vignetteImage(0, $value, $offset, $offset);

                    break;

                case 'wave':  //Wave effect

                    list($amp, $len) = $values;

                    $this->dst->waveImage($amp, $len);

                    break;

            }

        }

        return $this->dst;

    }

}
